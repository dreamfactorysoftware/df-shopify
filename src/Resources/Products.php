<?php

namespace DreamFactory\Core\Shopify\Resources;

use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use Illuminate\Support\Arr;

class Products extends BaseRestResource
{
    const RESOURCE_NAME = 'products';

    /**
     * Handle GET requests for products
     * 
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    protected function handleGET()
    {
        // Check if this is a request for a specific product or variants
        // Use DreamFactory's built-in resource path handling
        // Check if this is a variants request: /products/{id}/variants
        if (count($this->resourceArray) >= 2 && $this->resourceArray[1] === 'variants') {
            $productId = $this->resourceArray[0]; // The actual product ID
            return $this->getProductVariants($productId);
        }
        
        // Check if this is a request for a specific product: /products/{id}
        if (!empty($this->resourceArray) && !empty($this->resourceArray[0])) {
            $productId = $this->resourceArray[0];
            return $this->getProductById($productId);
        }
        try {
            // Get query parameters
            $limit = $this->request->getParameter(ApiOptions::LIMIT, 50);
            $offset = $this->request->getParameter(ApiOptions::OFFSET, 0);
            $fields = $this->request->getParameter(ApiOptions::FIELDS);
            $filter = $this->request->getParameter(ApiOptions::FILTER);
            $ids = $this->request->getParameter(ApiOptions::IDS);
            
            // Get Shopify service instance
            $shopifyService = $this->getService();
            
            // Prepare Shopify API parameters
            $params = [
                'limit' => min($limit, 250), // Shopify max is 250
            ];
            
            // Handle pagination with since_id instead of offset
            if ($offset > 0) {
                // For simplicity, we'll use a basic approach
                // In production, you'd want to implement proper cursor-based pagination
                $params['page'] = ceil($offset / $limit) + 1;
            }
            
            // Apply filters to Shopify API parameters (passed to Shopify, not filtered locally)
            $this->applyFiltersToParams($params, $filter, $ids);
            
            // Make direct HTTP call to Shopify (like successful curl test)
            $shopDomain = $shopifyService->getShopDomain();
            $accessToken = $shopifyService->getAccessToken();
            $apiVersion = $shopifyService->getApiVersion();
            
            $url = "https://{$shopDomain}/admin/api/{$apiVersion}/products.json";
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            

            
            // Use cURL for direct HTTP call
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Shopify-Access-Token: ' . $accessToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new InternalServerErrorException('Shopify API call failed: ' . $curlError);
            }
            
            $responseBody = json_decode($response, true);
            $shopifyProducts = $responseBody['products'] ?? [];
            

            
            // Transform Shopify product data to our format
            $products = [];
            foreach ($shopifyProducts as $product) {
                // Use lightweight mode for product lists to improve performance
                // Include large fields only if specifically requested via fields parameter
                $includeLargeFields = !empty($fields) && 
                    (strpos($fields, 'description') !== false || 
                     strpos($fields, 'images') !== false || 
                     strpos($fields, 'variants') !== false || 
                     strpos($fields, 'options') !== false);
                     
                $transformedProduct = $this->transformProduct($product, $includeLargeFields);
                
                // Apply field filtering if requested
                if (!empty($fields)) {
                    $fieldsArray = explode(',', $fields);
                    $transformedProduct = array_intersect_key($transformedProduct, array_flip($fieldsArray));
                }
                
                $products[] = $transformedProduct;
            }

            return [
                'resource' => $products
            ];

        } catch (\Exception $e) {
            \Log::error('Shopify API error: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to retrieve products from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Block all non-GET methods for read-only access
     */
    protected function handlePOST()
    {
        throw new BadRequestException('Creating products is not supported in read-only mode.');
    }

    protected function handlePUT()
    {
        throw new BadRequestException('Updating products is not supported in read-only mode.');
    }

    protected function handlePATCH()
    {
        throw new BadRequestException('Updating products is not supported in read-only mode.');
    }

    protected function handleDELETE()
    {
        throw new BadRequestException('Deleting products is not supported in read-only mode.');
    }

    /**
     * Get a specific product by ID
     * 
     * @param string $productId
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    protected function getProductById($productId)
    {
        try {
            $shopifyService = $this->getService();
            $shopDomain = $shopifyService->getShopDomain();
            $accessToken = $shopifyService->getAccessToken();
            $apiVersion = $shopifyService->getApiVersion();
            
            $url = "https://{$shopDomain}/admin/api/{$apiVersion}/products/{$productId}.json";
            

            
            // Use cURL for direct HTTP call
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Shopify-Access-Token: ' . $accessToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new InternalServerErrorException('Shopify API call failed: ' . $curlError);
            }
            
            if ($httpCode === 404) {
                throw new BadRequestException("Product with ID {$productId} not found.");
            }
            
            if ($httpCode !== 200) {
                $responseBody = json_decode($response, true);
                $errorMsg = $responseBody['errors'] ?? "HTTP {$httpCode}";
                throw new InternalServerErrorException("Failed to fetch product: {$errorMsg}");
            }
            
            $responseBody = json_decode($response, true);
            $product = $responseBody['product'] ?? null;
            
            if (!$product) {
                throw new BadRequestException("Product with ID {$productId} not found.");
            }
            
            // Transform single product to our format (include all fields for individual product requests)
            $transformedProduct = $this->transformProduct($product, true);
            
            return ['resource' => [$transformedProduct]];
            
        } catch (\Exception $e) {
            \Log::error('Error getting Shopify product: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to fetch product: ' . $e->getMessage());
        }
    }

    /**
     * Get variants for a specific product
     * 
     * @param string $productId
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    protected function getProductVariants($productId)
    {
        try {
            $shopifyService = $this->getService();
            $shopDomain = $shopifyService->getShopDomain();
            $accessToken = $shopifyService->getAccessToken();
            $apiVersion = $shopifyService->getApiVersion();
            
            $url = "https://{$shopDomain}/admin/api/{$apiVersion}/products/{$productId}/variants.json";
            

            
            // Use cURL for direct HTTP call
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Shopify-Access-Token: ' . $accessToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new InternalServerErrorException('Shopify API call failed: ' . $curlError);
            }
            
            if ($httpCode === 404) {
                throw new BadRequestException("Product with ID {$productId} not found.");
            }
            
            if ($httpCode !== 200) {
                $responseBody = json_decode($response, true);
                $errorMsg = $responseBody['errors'] ?? "HTTP {$httpCode}";
                throw new InternalServerErrorException("Failed to fetch variants: {$errorMsg}");
            }
            
            $responseBody = json_decode($response, true);
            $variants = $responseBody['variants'] ?? [];
            
            // Transform variants to our format
            $transformedVariants = [];
            foreach ($variants as $variant) {
                $transformedVariants[] = [
                    'id' => $variant['id'],
                    'product_id' => $variant['product_id'],
                    'title' => $variant['title'],
                    'price' => $variant['price'],
                    'sku' => $variant['sku'],
                    'position' => $variant['position'],
                    'inventory_policy' => $variant['inventory_policy'],
                    'compare_at_price' => $variant['compare_at_price'],
                    'fulfillment_service' => $variant['fulfillment_service'],
                    'inventory_management' => $variant['inventory_management'],
                    'option1' => $variant['option1'],
                    'option2' => $variant['option2'],
                    'option3' => $variant['option3'],
                    'taxable' => $variant['taxable'],
                    'barcode' => $variant['barcode'],
                    'grams' => $variant['grams'],
                    'weight' => $variant['weight'],
                    'weight_unit' => $variant['weight_unit'],
                    'inventory_item_id' => $variant['inventory_item_id'],
                    'inventory_quantity' => $variant['inventory_quantity'],
                    'old_inventory_quantity' => $variant['old_inventory_quantity'],
                    'requires_shipping' => $variant['requires_shipping'],
                    'admin_graphql_api_id' => $variant['admin_graphql_api_id'],
                    'image_id' => $variant['image_id'],
                    'created_at' => $variant['created_at'],
                    'updated_at' => $variant['updated_at']
                ];
            }
            
            return ['resource' => $transformedVariants];
            
        } catch (\Exception $e) {
            \Log::error('Error getting Shopify product variants: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to fetch variants: ' . $e->getMessage());
        }
    }

    /**
     * Transform a single Shopify product to our format
     */
    protected function transformProduct($product, $includeLargeFields = false)
    {
        // Start with essential fields (lightweight)
        $transformed = [
            'id' => $product['id'],
            'title' => $product['title'],
            'vendor' => $product['vendor'],
            'product_type' => $product['product_type'],
            'handle' => $product['handle'],
            'status' => $product['status'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at'],
            'published_at' => $product['published_at'],
            'tags' => $product['tags'],
            'variant_count' => count($product['variants'] ?? []),
            'image_count' => count($product['images'] ?? []),
            'has_variants' => count($product['variants'] ?? []) > 1,
        ];
        
        // Add large fields only if requested or if this is a single product request
        if ($includeLargeFields) {
            $transformed['description'] = $product['body_html'] ?? '';
            $transformed['images'] = $product['images'] ?? [];
            $transformed['variants'] = $product['variants'] ?? [];
            $transformed['options'] = $product['options'] ?? [];
            $transformed['template_suffix'] = $product['template_suffix'];
            $transformed['admin_graphql_api_id'] = $product['admin_graphql_api_id'];
        }
        
        return $transformed;
    }

    /**
     * Apply DreamFactory filters to Shopify API parameters
     * Maps standard filter syntax to Shopify's specific query parameters
     * 
     * @param array $params Shopify API parameters (modified by reference)
     * @param string|null $filter DreamFactory filter string
     * @param string|null $ids Comma-separated list of IDs
     */
    protected function applyFiltersToParams(array &$params, $filter = null, $ids = null)
    {
        // Handle IDs filter (standard DreamFactory pattern)
        if (!empty($ids)) {
            $params['ids'] = $ids;
            return; // When filtering by IDs, other filters are ignored
        }
        
        // Handle standard filter parameter
        if (!empty($filter)) {
            $this->parseFilterString($params, $filter);
        }
        
        // Handle Shopify-specific individual parameters
        $this->addShopifySpecificFilters($params);
    }

    /**
     * Parse DreamFactory filter string and map to Shopify parameters
     * Supports syntax like: "vendor='Nike' AND status='active'"
     */
    protected function parseFilterString(array &$params, $filter)
    {
        // Simple parser for common filter patterns
        // In production, you'd want a more robust SQL parser
        
        // Handle vendor filter: vendor='value' or vendor LIKE 'value'
        if (preg_match("/vendor\s*[=~]\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['vendor'] = $matches[1];
        }
        
        // Handle product_type filter
        if (preg_match("/product_type\s*[=~]\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['product_type'] = $matches[1];
        }
        
        // Handle status filter (active, archived, draft)
        if (preg_match("/status\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['status'] = $matches[1];
        }
        
        // Handle handle filter
        if (preg_match("/handle\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['handle'] = $matches[1];
        }
        
        // Handle date range filters
        // created_at >= '2023-01-01'
        if (preg_match("/created_at\s*>=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['created_at_min'] = $matches[1];
        }
        if (preg_match("/created_at\s*<=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['created_at_max'] = $matches[1];
        }
        
        // updated_at filters
        if (preg_match("/updated_at\s*>=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['updated_at_min'] = $matches[1];
        }
        if (preg_match("/updated_at\s*<=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['updated_at_max'] = $matches[1];
        }
        
        // published_at filters
        if (preg_match("/published_at\s*>=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['published_at_min'] = $matches[1];
        }
        if (preg_match("/published_at\s*<=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['published_at_max'] = $matches[1];
        }
        
        // Handle published_status (published, unpublished, any)
        if (preg_match("/published_status\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['published_status'] = $matches[1];
        }
        

    }

    /**
     * Add Shopify-specific filters from individual request parameters
     */
    protected function addShopifySpecificFilters(array &$params)
    {
        // Allow direct Shopify parameters for advanced filtering
        $shopifyParams = [
            'vendor', 'product_type', 'handle', 'status', 'published_status',
            'created_at_min', 'created_at_max', 'updated_at_min', 'updated_at_max',
            'published_at_min', 'published_at_max', 'collection_id'
        ];
        
        foreach ($shopifyParams as $param) {
            $value = $this->request->getParameter($param);
            if (!empty($value)) {
                $params[$param] = $value;
            }
        }
    }

    /**
     * Generate API documentation for this resource
     */
    protected function getApiDocPaths()
    {
        $serviceName = $this->getServiceName();
        $capitalized = camelize($serviceName);
        $resourceName = strtolower($this->name);

        $paths = [
            '/' . $resourceName => [
                'get' => [
                    'summary'     => 'Retrieve Shopify products',
                    'description' => 'Get a list of products from your Shopify store. Supports filtering with limit, offset, fields, and filter parameters. Filters are passed directly to Shopify for efficient server-side filtering. For better performance with large product lists, use the fields parameter to limit returned data (e.g., fields=id,title,price). Large fields like description, images, variants, and options are excluded by default from lists but included when specifically requested.',
                    'operationId' => 'get' . $capitalized . 'Products',
                    'parameters'  => [
                        [
                            'name'        => 'limit',
                            'in'          => 'query',
                            'schema'      => ['type' => 'integer'],
                            'description' => 'Maximum number of products to return (default: 50)'
                        ],
                        [
                            'name'        => 'offset', 
                            'in'          => 'query',
                            'schema'      => ['type' => 'integer'],
                            'description' => 'Number of products to skip (default: 0)'
                        ],
                        [
                            'name'        => 'fields',
                            'in'          => 'query', 
                            'schema'      => ['type' => 'string'],
                            'description' => 'Comma-separated list of fields to return (e.g., "id,title,vendor")'
                        ],
                        [
                            'name'        => 'filter',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'SQL-like filter expression (e.g., "vendor=\'Nike\' AND status=\'active\'"). Supported fields: vendor, product_type, status, handle, created_at, updated_at, published_at, published_status'
                        ],
                        [
                            'name'        => 'ids',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Comma-separated list of product IDs to retrieve'
                        ],
                        [
                            'name'        => 'vendor',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by product vendor (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'product_type',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by product type (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'status',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by status: active, archived, draft (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'published_status',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by published status: published, unpublished, any (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'created_at_min',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter products created after this date (ISO 8601 format)'
                        ],
                        [
                            'name'        => 'created_at_max',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter products created before this date (ISO 8601 format)'
                        ],
                        [
                            'name'        => 'collection_id',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter products by collection ID (Shopify-specific parameter)'
                        ]
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Success',
                            'content'     => [
                                'application/json' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'resource' => [
                                                'type'        => 'array',
                                                'description' => 'Array of product objects',
                                                'items'       => [
                                                    'type'       => 'object',
                                                    'properties' => [
                                                        'id'             => ['type' => 'integer', 'description' => 'Product ID'],
                                                        'title'          => ['type' => 'string', 'description' => 'Product title'],
                                                        'vendor'         => ['type' => 'string', 'description' => 'Product vendor'],
                                                        'product_type'   => ['type' => 'string', 'description' => 'Product type'],
                                                        'handle'         => ['type' => 'string', 'description' => 'Product handle/slug'],
                                                        'status'         => ['type' => 'string', 'description' => 'Product status'],
                                                        'created_at'     => ['type' => 'string', 'description' => 'Creation date'],
                                                        'updated_at'     => ['type' => 'string', 'description' => 'Last update date'],
                                                        'tags'           => ['type' => 'string', 'description' => 'Product tags'],
                                                        'variants_count' => ['type' => 'integer', 'description' => 'Number of product variants'],
                                                        'images_count'   => ['type' => 'integer', 'description' => 'Number of product images']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '400' => ['description' => 'Bad Request'],
                        '401' => ['description' => 'Unauthorized'],
                        '500' => ['description' => 'Internal Server Error']
                    ]
                ]
            ],
            '/' . $resourceName . '/{id}' => [
                'get' => [
                    'summary'     => 'Retrieve a specific Shopify product',
                    'description' => 'Get detailed information about a single product by its ID, including variants, images, and options.',
                    'parameters'  => [
                        [
                            'name'        => 'id',
                            'in'          => 'path',
                            'required'    => true,
                            'type'        => 'integer',
                            'description' => 'Product ID',
                        ],
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Product details',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'resource' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'id'           => ['type' => 'integer', 'description' => 'Product ID'],
                                                'title'        => ['type' => 'string', 'description' => 'Product title'],
                                                'description'  => ['type' => 'string', 'description' => 'Product description'],
                                                'variants'     => ['type' => 'array', 'description' => 'Product variants'],
                                                'images'       => ['type' => 'array', 'description' => 'Product images'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Product not found'],
                        '401' => ['description' => 'Unauthorized'],
                        '500' => ['description' => 'Internal Server Error']
                    ],
                ],
            ],
            '/' . $resourceName . '/{id}/variants' => [
                'get' => [
                    'summary'     => 'Retrieve product variants',
                    'description' => 'Get all variants for a specific product, including pricing, inventory, and option details.',
                    'parameters'  => [
                        [
                            'name'        => 'id',
                            'in'          => 'path',
                            'required'    => true,
                            'type'        => 'integer',
                            'description' => 'Product ID',
                        ],
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Product variants',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'resource' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'id'                  => ['type' => 'integer', 'description' => 'Variant ID'],
                                                'product_id'          => ['type' => 'integer', 'description' => 'Parent product ID'],
                                                'title'               => ['type' => 'string', 'description' => 'Variant title'],
                                                'price'               => ['type' => 'string', 'description' => 'Variant price'],
                                                'sku'                 => ['type' => 'string', 'description' => 'SKU code'],
                                                'inventory_quantity'  => ['type' => 'integer', 'description' => 'Available quantity'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Product not found'],
                        '401' => ['description' => 'Unauthorized'],
                        '500' => ['description' => 'Internal Server Error']
                    ],
                ],
            ]
        ];

        return $paths;
    }

    /**
     * @inheritDoc
     */
    public function getApiDocInfo()
    {
        $paths = $this->getApiDocPaths();
        return ['paths' => $paths, 'components' => []];
    }
} 