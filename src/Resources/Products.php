<?php

namespace DreamFactory\Core\Shopify\Resources;

use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Shopify\GraphQL\QueryBuilder;
use DreamFactory\Core\Shopify\GraphQL\ResponseTransformer;
use Shopify\Exception\ShopifyException;
use Illuminate\Support\Arr;

class Products extends BaseRestResource
{
    const RESOURCE_NAME = 'products';

    /**
     * Handle GET requests for products using GraphQL
     */
    protected function handleGET()
    {
        try {
            $limit = $this->request->getParameter(ApiOptions::LIMIT, 50);
            $offset = $this->request->getParameter(ApiOptions::OFFSET, 0);
            $fields = $this->request->getParameter(ApiOptions::FIELDS);
            $filter = $this->request->getParameter(ApiOptions::FILTER);
            $ids = $this->request->getParameter(ApiOptions::IDS);
            
            // Get Shopify service credentials (same as working REST endpoints)
            $shopifyService = $this->getService();
            $shopDomain = $shopifyService->getShopDomain();
            $accessToken = $shopifyService->getAccessToken();
            
            // Parse resource ID from DreamFactory routing
            $resourceId = null;
            if (!empty($this->resourceId) && is_numeric($this->resourceId)) {
                $resourceId = $this->resourceId;
            } elseif (isset($this->resourceArray[0]) && !empty($this->resourceArray[0])) {
                $resourceId = $this->resourceArray[0];
            }
            $subResource = isset($this->resourceArray[1]) ? $this->resourceArray[1] : null;
            
            // Route to appropriate handler
            if ($resourceId) {
                if ($subResource === 'variants') {
                    // GET /products/{id}/variants
                    return $this->getProductVariants($resourceId, $shopDomain, $accessToken, $fields);
                } else {
                    // GET /products/{id}
                    return $this->getSingleProduct($resourceId, $shopDomain, $accessToken, $fields);
                }
            } else {
                // GET /products
                return $this->getProducts($shopDomain, $accessToken, $limit, $offset, $fields, $filter, $ids);
            }
            
        } catch (ShopifyException $e) {
            \Log::error('Shopify GraphQL API error: ' . $e->getMessage());
            throw new \DreamFactory\Core\Exceptions\InternalServerErrorException('Shopify API error: ' . $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Products API error: ' . $e->getMessage());
            throw new \DreamFactory\Core\Exceptions\InternalServerErrorException('Failed to fetch products: ' . $e->getMessage());
        }
    }

    /**
     * Get products list using GraphQL
     */
    private function getProducts($shopDomain, $accessToken, $limit, $offset, $fields, $filter, $ids)
    {
        // Build filters from DreamFactory parameters
        $filters = $this->parseFilters($filter, $ids);
        
        // Handle pagination with cursor (GraphQL doesn't use offset)
        $cursor = $this->request->getParameter('cursor');
        if ($offset > 0 && !$cursor) {
            // For offset-based pagination, we'd need to fetch and skip
            // For simplicity, we'll use cursor-based pagination
            \Log::warning('Offset-based pagination not optimal with GraphQL. Use cursor parameter instead.');
        }
        
        // Build GraphQL query
        $query = QueryBuilder::buildProductsQuery($limit, $fields, $cursor, $filters);
        
        \Log::info('Executing GraphQL products query', ['query' => $query]);
        
        // Use cURL for GraphQL (same proven auth as working REST endpoints)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://{$shopDomain}/admin/api/{$this->getService()->getApiVersion()}/graphql.json",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query]),
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
            throw new \DreamFactory\Core\Exceptions\InternalServerErrorException('Shopify GraphQL API call failed: ' . $curlError);
        }
        
        $responseData = json_decode($response, true);
        
        // Check for GraphQL errors
        if (isset($responseData['errors'])) {
            $errorMessage = 'GraphQL errors: ' . json_encode($responseData['errors']);
            \Log::error($errorMessage);
            throw new \DreamFactory\Core\Exceptions\BadRequestException($errorMessage);
        }
        
        // Transform GraphQL response to REST format
        return ResponseTransformer::transformProductsResponse($responseData);
    }

    /**
     * Get single product using GraphQL
     */
    private function getSingleProduct($productId, $shopDomain, $accessToken, $fields)
    {
        // Convert numeric ID to GraphQL global ID format
        $graphqlId = "gid://shopify/Product/{$productId}";
        
        // Build GraphQL query
        $query = QueryBuilder::buildProductQuery($graphqlId, $fields);
        
        \Log::info('Executing GraphQL single product query', [
            'query' => $query, 
            'product_id' => $productId
        ]);
        
        // Use cURL for GraphQL (same proven auth as working REST endpoints)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://{$shopDomain}/admin/api/{$this->getService()->getApiVersion()}/graphql.json",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query]),
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
            throw new \DreamFactory\Core\Exceptions\InternalServerErrorException('Shopify GraphQL API call failed: ' . $curlError);
        }
        
        $responseData = json_decode($response, true);
        
        // Check for GraphQL errors
        if (isset($responseData['errors'])) {
            $errorMessage = 'GraphQL errors: ' . json_encode($responseData['errors']);
            \Log::error($errorMessage);
            throw new \DreamFactory\Core\Exceptions\BadRequestException($errorMessage);
        }
        
        // Transform GraphQL response to REST format
        return ResponseTransformer::transformSingleProductResponse($responseData);
    }

    /**
     * Get product variants using GraphQL
     */
    private function getProductVariants($productId, $shopDomain, $accessToken, $fields)
    {
        // Convert numeric ID to GraphQL global ID format
        $graphqlId = "gid://shopify/Product/{$productId}";
        
        // Build GraphQL query for product variants
        $query = "
            query getProductVariants {
                product(id: \"{$graphqlId}\") {
                    id
                    title
                    variants(first: 100) {
                        edges {
                            node {
                                id
                                title
                                price
                                sku
                                inventoryQuantity
                                selectedOptions {
                                    name
                                    value
                                }
                                taxable
                                barcode
                                createdAt
                                updatedAt
                            }
                        }
                    }
                }
            }
        ";
        
        \Log::info('Executing GraphQL product variants query', [
            'query' => $query,
            'product_id' => $productId
        ]);
        
        // Use cURL for GraphQL (same proven auth as working REST endpoints)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://{$shopDomain}/admin/api/{$this->getService()->getApiVersion()}/graphql.json",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query]),
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
            throw new \DreamFactory\Core\Exceptions\InternalServerErrorException('Shopify GraphQL API call failed: ' . $curlError);
        }
        
        $responseData = json_decode($response, true);
        
        // Check for GraphQL errors
        if (isset($responseData['errors'])) {
            $errorMessage = 'GraphQL errors: ' . json_encode($responseData['errors']);
            \Log::error($errorMessage);
            throw new \DreamFactory\Core\Exceptions\BadRequestException($errorMessage);
        }
        
        // Transform variants to REST format
        $variants = [];
        if (isset($responseData['data']['product']['variants']['edges'])) {
            foreach ($responseData['data']['product']['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                $variants[] = [
                    'id' => ResponseTransformer::extractNumericId($variant['id']),
                    'product_id' => $productId,
                    'title' => $variant['title'] ?? '',
                    'price' => $variant['price'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'inventory_quantity' => $variant['inventoryQuantity'] ?? 0,
                    'option1' => $variant['selectedOptions'][0]['value'] ?? null,
                    'option2' => $variant['selectedOptions'][1]['value'] ?? null,
                    'option3' => $variant['selectedOptions'][2]['value'] ?? null,
                    'taxable' => $variant['taxable'] ?? true,
                    'barcode' => $variant['barcode'] ?? '',
                    'created_at' => $variant['createdAt'] ?? '',
                    'updated_at' => $variant['updatedAt'] ?? ''
                ];
            }
        }
        
        return ['resource' => $variants];
    }

    /**
     * Parse DreamFactory filters into GraphQL format
     */
    private function parseFilters($filter, $ids)
    {
        $filters = [];
        
        // Handle IDs parameter
        if (!empty($ids)) {
            $idList = is_array($ids) ? $ids : explode(',', $ids);
            $graphqlIds = array_map(function($id) {
                return "gid://shopify/Product/{$id}";
            }, $idList);
            // Note: GraphQL doesn't support direct ID filtering in products query
            // This would need to be handled differently, possibly with multiple single queries
        }
        
        // Handle filter parameter
        if (!empty($filter)) {
            // Parse SQL-like filter into GraphQL query format
            $filterParts = $this->parseSqlFilter($filter);
            $filters = array_merge($filters, $filterParts);
        }
        
        // Handle additional Shopify-specific parameters
        $vendor = $this->request->getParameter('vendor');
        if ($vendor) {
            $filters['vendor'] = $vendor;
        }
        
        $productType = $this->request->getParameter('product_type');
        if ($productType) {
            $filters['product_type'] = $productType;
        }
        
        $status = $this->request->getParameter('status');
        if ($status) {
            $filters['status'] = strtoupper($status);
        }
        
        return $filters;
    }

    /**
     * Parse SQL-like filter into key-value pairs
     */
    private function parseSqlFilter($filter)
    {
        $filters = [];
        
        // Simple parsing for common filters like "vendor='Nike' AND status='active'"
        // This is a basic implementation - could be enhanced for complex filters
        
        if (preg_match("/vendor\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $filters['vendor'] = $matches[1];
        }
        
        if (preg_match("/product_type\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $filters['product_type'] = $matches[1];
        }
        
        if (preg_match("/status\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $filters['status'] = strtoupper($matches[1]);
        }
        
        return $filters;
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