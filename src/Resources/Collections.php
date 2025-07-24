<?php

namespace DreamFactory\Core\Shopify\Resources;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Utility\ResponseFactory;

class Collections extends BaseRestResource
{
    const RESOURCE_NAME = 'collections';
    
    /**
     * Array of resource information for service discovery.
     *
     * @return array
     */
    public function getResources()
    {
        return [
            [
                'name' => 'collections',
                'label' => 'Collections',
                'description' => 'Operations for Shopify collections',
            ],
        ];
    }

    /**
     * Handle GET requests for collections
     */
    protected function handleGET()
    {
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
            if ($subResource === 'products') {
                // GET /collections/{id}/products
                return $this->getCollectionProducts($resourceId);
            } else {
                // GET /collections/{id}
                return $this->getCollectionById($resourceId);
            }
        } else {

            // GET /collections
            return $this->getCollections();
        }
    }

    /**
     * Get all collections (both smart and custom collections)
     */
    private function getCollections()
    {
        try {
            $service = $this->getService();
            $shopDomain = $service->getShopDomain();
            $accessToken = $service->getAccessToken();
            $apiVersion = $service->getApiVersion();

            // Build query parameters
            $params = [];
            
            // Apply filters and pagination
            $this->applyFiltersToParams($params);

            $queryString = !empty($params) ? '?' . http_build_query($params) : '';
            
            // Shopify has separate endpoints for smart and custom collections
            $smartCollectionsUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/smart_collections.json{$queryString}";
            $customCollectionsUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/custom_collections.json{$queryString}";

            $allCollections = [];

            // Get smart collections
            $smartCollections = $this->fetchCollectionsFromUrl($smartCollectionsUrl, $accessToken, 'smart_collections');
            if ($smartCollections) {
                $allCollections = array_merge($allCollections, $smartCollections);
            }

            // Get custom collections
            $customCollections = $this->fetchCollectionsFromUrl($customCollectionsUrl, $accessToken, 'custom_collections');
            if ($customCollections) {
                $allCollections = array_merge($allCollections, $customCollections);
            }

            // Transform collections data
            $collections = array_map([$this, 'transformCollection'], $allCollections);

            return ResponseFactory::create(['resource' => $collections]);

        } catch (\Exception $e) {
            \Log::error('Shopify Collections Error: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve collections from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to fetch collections from a specific URL
     */
    private function fetchCollectionsFromUrl($url, $accessToken, $expectedKey)
    {
        // Make API call using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Shopify-Access-Token: ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            \Log::error('Shopify Collections API Error', [
                'http_code' => $httpCode,
                'response' => $response,
                'url' => $url
            ]);
            throw new \Exception("Failed to retrieve collections from Shopify: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data[$expectedKey])) {
            \Log::warning('Shopify Collections API returned unexpected structure', [
                'url' => $url,
                'response_keys' => array_keys($data ?: [])
            ]);
            return [];
        }

        return $data[$expectedKey];
    }

    /**
     * Get collection by ID (tries both smart and custom collections)
     */
    private function getCollectionById($collectionId)
    {
        try {
            $service = $this->getService();
            $shopDomain = $service->getShopDomain();
            $accessToken = $service->getAccessToken();
            $apiVersion = $service->getApiVersion();

            // Try smart collections first
            $smartCollectionUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/smart_collections/{$collectionId}.json";
            $collection = $this->fetchSingleCollectionFromUrl($smartCollectionUrl, $accessToken, 'smart_collection');
            
            if (!$collection) {
                // Try custom collections if not found in smart collections
                $customCollectionUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/custom_collections/{$collectionId}.json";
                $collection = $this->fetchSingleCollectionFromUrl($customCollectionUrl, $accessToken, 'custom_collection');
            }

            if (!$collection) {
                throw new \Exception("Collection {$collectionId} not found");
            }

            // Transform collection data (include all fields for individual requests)
            $transformedCollection = $this->transformCollection($collection, true);

            return ResponseFactory::create($transformedCollection);

        } catch (\Exception $e) {
            \Log::error('Shopify Collection Error: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve collection from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to fetch a single collection from a specific URL
     */
    private function fetchSingleCollectionFromUrl($url, $accessToken, $expectedKey)
    {
        // Make API call using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Shopify-Access-Token: ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404) {
            // Not found in this endpoint, return null to try the other endpoint
            return null;
        }

        if ($httpCode !== 200) {
            \Log::error('Shopify Collection API Error', [
                'http_code' => $httpCode,
                'response' => $response,
                'url' => $url
            ]);
            throw new \Exception("Failed to retrieve collection from Shopify: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data[$expectedKey])) {
            return null;
        }

        return $data[$expectedKey];
    }

    /**
     * Get products in a collection (tries both smart and custom collections)
     */
    private function getCollectionProducts($collectionId)
    {
        try {
            $service = $this->getService();
            $shopDomain = $service->getShopDomain();
            $accessToken = $service->getAccessToken();
            $apiVersion = $service->getApiVersion();



            // Build query parameters
            $params = [];
            
            // Apply filters and pagination
            $this->applyFiltersToParams($params);

            $queryString = !empty($params) ? '?' . http_build_query($params) : '';
            
            // Use unified collections endpoint (works for both smart and custom)
            $collectionUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/collections/{$collectionId}/products.json{$queryString}";
            $products = $this->fetchCollectionProductsFromUrl($collectionUrl, $accessToken);
            
            if ($products === null) {
                \Log::error('Collection products not found', [
                    'collection_id' => $collectionId,
                    'url' => $collectionUrl
                ]);
                throw new \Exception("Collection products not found");
            }

            // Transform products data using simple transformer
            $transformedProducts = array_map(function($product) {
                return $this->transformProduct($product, false); // Lightweight for lists
            }, $products);



            return ResponseFactory::create(['resource' => $transformedProducts]);

        } catch (\Exception $e) {
            \Log::error('Shopify Collection Products Error: ' . $e->getMessage(), [
                'collection_id' => $collectionId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to retrieve collection products from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to fetch products from a collection URL
     */
    private function fetchCollectionProductsFromUrl($url, $accessToken)
    {
        // Make API call using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Shopify-Access-Token: ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            \Log::error('Shopify Collection Products cURL Error', [
                'url' => $url,
                'curl_error' => $curlError
            ]);
            throw new \Exception("Failed to connect to Shopify API: {$curlError}");
        }

        if ($httpCode === 404) {
            // Not found - return null
            return null;
        }

        if ($httpCode !== 200) {
            \Log::error('Shopify Collection Products API Error', [
                'http_code' => $httpCode,
                'response' => $response,
                'url' => $url
            ]);
            throw new \Exception("Failed to retrieve collection products from Shopify: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['products'])) {
            \Log::warning('Collection products API returned unexpected structure', [
                'url' => $url,
                'response_keys' => array_keys($data ?: []),
                'response' => $response
            ]);
            return null;
        }



        return $data['products'];
    }

    /**
     * Transform collection data
     */
    private function transformCollection($collection, $includeLargeFields = false)
    {
        // Determine collection type based on presence of rules (smart collections have rules)
        $isSmartCollection = isset($collection['rules']) && !empty($collection['rules']);
        $collectionType = $isSmartCollection ? 'smart' : 'custom';

        // Start with essential fields (lightweight)
        $transformed = [
            'id' => $collection['id'],
            'title' => $collection['title'] ?? null,
            'handle' => $collection['handle'] ?? null,
            'published' => $collection['published'] ?? false,
            'collection_type' => $collectionType,
            'sort_order' => $collection['sort_order'] ?? null,
            'products_count' => $collection['products_count'] ?? 0,
            'created_at' => $collection['created_at'] ?? null,
            'updated_at' => $collection['updated_at'] ?? null,
            'published_at' => $collection['published_at'] ?? null,
        ];

        // Add large fields only if requested or if this is a single collection request
        if ($includeLargeFields) {
            $transformed['description'] = $collection['description'] ?? null;
            $transformed['template_suffix'] = $collection['template_suffix'] ?? null;
            $transformed['body_html'] = $collection['body_html'] ?? null;
            $transformed['image'] = $collection['image'] ?? null;
            $transformed['published_scope'] = $collection['published_scope'] ?? 'web';
            
            // Smart collection specific fields
            if ($isSmartCollection) {
                $transformed['rules'] = $collection['rules'] ?? [];
                $transformed['disjunctive'] = $collection['disjunctive'] ?? false;
            }
        }

        return $transformed;
    }

    /**
     * Simple product transformer for collection products
     */
    private function transformProduct($product, $includeLargeFields = false)
    {
        // Basic product fields for collection listings
        $transformed = [
            'id' => $product['id'],
            'title' => $product['title'] ?? null,
            'vendor' => $product['vendor'] ?? null,
            'product_type' => $product['product_type'] ?? null,
            'handle' => $product['handle'] ?? null,
            'status' => $product['status'] ?? null,
            'created_at' => $product['created_at'] ?? null,
            'updated_at' => $product['updated_at'] ?? null,
            'published_at' => $product['published_at'] ?? null,
            'tags' => $product['tags'] ?? '',
            'variant_count' => isset($product['variants']) ? count($product['variants']) : 0,
            'image_count' => isset($product['images']) ? count($product['images']) : 0,
            'has_variants' => isset($product['variants']) && count($product['variants']) > 1,
        ];

        if ($includeLargeFields) {
            $transformed['description'] = $product['body_html'] ?? null;
            $transformed['images'] = $product['images'] ?? [];
            $transformed['variants'] = $product['variants'] ?? [];
            $transformed['options'] = $product['options'] ?? [];
        }

        return $transformed;
    }

    /**
     * Apply filters and pagination to API parameters
     */
    private function applyFiltersToParams(&$params)
    {
        // Standard DreamFactory parameters
        if ($limit = $this->request->getParameter('limit')) {
            $params['limit'] = (int)$limit;
        }

        if ($offset = $this->request->getParameter('offset')) {
            // Shopify uses since_id for pagination, but we'll handle offset differently
            // For now, we'll just pass it as a parameter
        }

        if ($fields = $this->request->getParameter('fields')) {
            $params['fields'] = $fields;
        }

        // Handle filter parameter
        if ($filter = $this->request->getParameter('filter')) {
            $this->parseFilterString($filter, $params);
        }

        // Handle ids parameter
        if ($ids = $this->request->getParameter('ids')) {
            $params['ids'] = $ids;
        }

        // Add Shopify-specific collection filters
        $this->addShopifySpecificFilters($params);
    }

    /**
     * Parse DreamFactory filter string
     */
    private function parseFilterString($filter, &$params)
    {
        // Simple parsing for common patterns like "field=value" or "field eq 'value'"
        if (preg_match('/(\w+)\s*(?:=|eq)\s*[\'"]?([^\'"]+)[\'"]?/i', $filter, $matches)) {
            $field = $matches[1];
            $value = $matches[2];

            switch ($field) {
                case 'title':
                    $params['title'] = $value;
                    break;
                case 'handle':
                    $params['handle'] = $value;
                    break;
                case 'published':
                    $params['published'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                    break;
                case 'collection_type':
                    $params['collection_type'] = $value;
                    break;
            }
        }
    }

    /**
     * Add Shopify-specific collection filters
     */
    private function addShopifySpecificFilters(&$params)
    {
        // Direct Shopify collection parameters
        $shopifyParams = [
            'title', 'handle', 'published', 'collection_type',
            'created_at_min', 'created_at_max', 'updated_at_min', 'updated_at_max',
            'published_at_min', 'published_at_max', 'since_id'
        ];

        foreach ($shopifyParams as $param) {
            if ($value = $this->request->getParameter($param)) {
                $params[$param] = $value;
            }
        }
    }

    /**
     * Block non-GET methods for read-only access
     */
    protected function handlePOST()
    {
        throw new BadRequestException('Creating collections is not supported in this read-only implementation.');
    }

    protected function handlePUT()
    {
        throw new BadRequestException('Updating collections is not supported in this read-only implementation.');
    }

    protected function handlePATCH()
    {
        throw new BadRequestException('Updating collections is not supported in this read-only implementation.');
    }

    protected function handleDELETE()
    {
        throw new BadRequestException('Deleting collections is not supported in this read-only implementation.');
    }

    /**
     * Get API documentation paths
     */
    public function getApiDocPaths()
    {
        return [
            '/collections' => [
                'get' => [
                    'summary' => 'List collections',
                    'description' => 'Retrieve a list of Shopify collections with optional filtering and pagination',
                    'parameters' => [
                        [
                            'name' => 'limit',
                            'in' => 'query',
                            'description' => 'Maximum number of collections to return',
                            'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 250, 'default' => 50]
                        ],
                        [
                            'name' => 'offset',
                            'in' => 'query',
                            'description' => 'Number of collections to skip',
                            'schema' => ['type' => 'integer', 'minimum' => 0]
                        ],
                        [
                            'name' => 'fields',
                            'in' => 'query',
                            'description' => 'Comma-separated list of fields to include',
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'filter',
                            'in' => 'query',
                            'description' => 'Filter string (e.g., "title=Summer Collection")',
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'ids',
                            'in' => 'query',
                            'description' => 'Comma-separated list of collection IDs',
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'title',
                            'in' => 'query',
                            'description' => 'Filter by collection title',
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'handle',
                            'in' => 'query',
                            'description' => 'Filter by collection handle',
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'published',
                            'in' => 'query',
                            'description' => 'Filter by published status',
                            'schema' => ['type' => 'boolean']
                        ],
                        [
                            'name' => 'collection_type',
                            'in' => 'query',
                            'description' => 'Filter by collection type (smart, manual)',
                            'schema' => ['type' => 'string', 'enum' => ['smart', 'manual']]
                        ],
                        [
                            'name' => 'created_at_min',
                            'in' => 'query',
                            'description' => 'Filter by minimum creation date (ISO 8601)',
                            'schema' => ['type' => 'string', 'format' => 'date-time']
                        ],
                        [
                            'name' => 'created_at_max',
                            'in' => 'query',
                            'description' => 'Filter by maximum creation date (ISO 8601)',
                            'schema' => ['type' => 'string', 'format' => 'date-time']
                        ],
                        [
                            'name' => 'updated_at_min',
                            'in' => 'query',
                            'description' => 'Filter by minimum update date (ISO 8601)',
                            'schema' => ['type' => 'string', 'format' => 'date-time']
                        ],
                        [
                            'name' => 'updated_at_max',
                            'in' => 'query',
                            'description' => 'Filter by maximum update date (ISO 8601)',
                            'schema' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'List of collections',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'resource' => [
                                                'type' => 'array',
                                                'items' => ['$ref' => '#/components/schemas/Collection']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/collections/{id}' => [
                'get' => [
                    'summary' => 'Get collection details',
                    'description' => 'Retrieve detailed information about a specific collection',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'Collection ID',
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Collection details',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Collection']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/collections/{id}/products' => [
                'get' => [
                    'summary' => 'Get products in collection',
                    'description' => 'Retrieve all products that belong to a specific collection',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'Collection ID',
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'limit',
                            'in' => 'query',
                            'description' => 'Maximum number of products to return',
                            'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 250, 'default' => 50]
                        ],
                        [
                            'name' => 'fields',
                            'in' => 'query',
                            'description' => 'Comma-separated list of fields to include',
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'List of products in the collection',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'resource' => [
                                                'type' => 'array',
                                                'items' => ['$ref' => '#/components/schemas/Product']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
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