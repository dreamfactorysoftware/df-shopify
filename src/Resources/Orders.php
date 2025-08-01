<?php

namespace DreamFactory\Core\Shopify\Resources;

use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use Illuminate\Support\Arr;

class Orders extends BaseRestResource
{
    const RESOURCE_NAME = 'orders';

    /**
     * Handle GET requests for orders
     * 
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    protected function handleGET()
    {
        // Check if this is a request for a specific order: /orders/{id}
        if (!empty($this->resourceArray) && !empty($this->resourceArray[0])) {
            $orderId = $this->resourceArray[0];
            return $this->getOrderById($orderId);
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
                'status' => 'any', // Default to show all order statuses
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
            
            $url = "https://{$shopDomain}/admin/api/{$apiVersion}/orders.json";
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
            $shopifyOrders = $responseBody['orders'] ?? [];
            

            
            // Transform Shopify order data to our format
            $orders = [];
            foreach ($shopifyOrders as $order) {
                // Use lightweight mode for order lists to improve performance
                // Include large fields only if specifically requested via fields parameter
                $includeLargeFields = !empty($fields) && 
                    (strpos($fields, 'line_items') !== false || 
                     strpos($fields, 'billing_address') !== false || 
                     strpos($fields, 'shipping_address') !== false ||
                     strpos($fields, 'shipping_lines') !== false);
                     
                $transformedOrder = $this->transformOrder($order, $includeLargeFields);
                
                // Apply field filtering if requested
                if (!empty($fields)) {
                    $fieldsArray = explode(',', $fields);
                    $transformedOrder = array_intersect_key($transformedOrder, array_flip($fieldsArray));
                }
                
                $orders[] = $transformedOrder;
            }

            return [
                'resource' => $orders
            ];

        } catch (\Exception $e) {
            \Log::error('Shopify Orders API error: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to retrieve orders from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific order by ID
     * 
     * @param string $orderId
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    protected function getOrderById($orderId)
    {
        try {
            $shopifyService = $this->getService();
            $shopDomain = $shopifyService->getShopDomain();
            $accessToken = $shopifyService->getAccessToken();
            $apiVersion = $shopifyService->getApiVersion();
            
            $url = "https://{$shopDomain}/admin/api/{$apiVersion}/orders/{$orderId}.json";
            

            
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
                throw new BadRequestException("Order with ID {$orderId} not found.");
            }
            
            if ($httpCode !== 200) {
                $responseBody = json_decode($response, true);
                $errorMsg = $responseBody['errors'] ?? "HTTP {$httpCode}";
                throw new InternalServerErrorException("Failed to fetch order: {$errorMsg}");
            }
            
            $responseBody = json_decode($response, true);
            $order = $responseBody['order'] ?? null;
            
            if (!$order) {
                throw new BadRequestException("Order with ID {$orderId} not found.");
            }
            
            // Transform single order to our format (include all fields for individual order requests)
            $transformedOrder = $this->transformOrder($order, true);
            
            return ['resource' => [$transformedOrder]];
            
        } catch (\Exception $e) {
            \Log::error('Error getting Shopify order: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to fetch order: ' . $e->getMessage());
        }
    }

    /**
     * Block all non-GET methods for read-only access
     */
    protected function handlePOST()
    {
        throw new BadRequestException('Creating orders is not supported in read-only mode.');
    }

    protected function handlePUT()
    {
        throw new BadRequestException('Updating orders is not supported in read-only mode.');
    }

    protected function handlePATCH()
    {
        throw new BadRequestException('Updating orders is not supported in read-only mode.');
    }

    protected function handleDELETE()
    {
        throw new BadRequestException('Deleting orders is not supported in read-only mode.');
    }

    /**
     * Transform a single Shopify order to our format
     */
    protected function transformOrder($order, $includeLargeFields = false)
    {
        // Start with essential fields (lightweight)
        $transformed = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'name' => $order['name'],
            'email' => $order['email'],
            'phone' => $order['phone'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'],
            'processed_at' => $order['processed_at'],
            'financial_status' => $order['financial_status'],
            'fulfillment_status' => $order['fulfillment_status'],
            'confirmed' => $order['confirmed'],
            'total_price' => $order['total_price'],
            'subtotal_price' => $order['subtotal_price'],
            'total_tax' => $order['total_tax'],
            'currency' => $order['currency'],
            'customer_id' => $order['customer']['id'] ?? null,
            'line_items_count' => count($order['line_items'] ?? []),
            'tags' => $order['tags'],
        ];
        
        // Add large fields only if requested or if this is a single order request
        if ($includeLargeFields) {
            $transformed['line_items'] = $order['line_items'] ?? [];
            $transformed['billing_address'] = $order['billing_address'] ?? null;
            $transformed['shipping_address'] = $order['shipping_address'] ?? null;
            $transformed['shipping_lines'] = $order['shipping_lines'] ?? [];
            $transformed['tax_lines'] = $order['tax_lines'] ?? [];
            $transformed['discount_codes'] = $order['discount_codes'] ?? [];
            $transformed['customer'] = $order['customer'] ?? null;
            $transformed['note'] = $order['note'];
            $transformed['note_attributes'] = $order['note_attributes'] ?? [];
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
     * Supports syntax like: "financial_status='paid' AND created_at >= '2023-01-01'"
     */
    protected function parseFilterString(array &$params, $filter)
    {
        // Handle financial_status filter
        if (preg_match("/financial_status\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['financial_status'] = $matches[1];
        }
        
        // Handle fulfillment_status filter
        if (preg_match("/fulfillment_status\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['fulfillment_status'] = $matches[1];
        }
        
        // Handle status filter (open, closed, cancelled, any)
        if (preg_match("/status\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['status'] = $matches[1];
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
        
        // processed_at filters
        if (preg_match("/processed_at\s*>=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['processed_at_min'] = $matches[1];
        }
        if (preg_match("/processed_at\s*<=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['processed_at_max'] = $matches[1];
        }
        

    }

    /**
     * Add Shopify-specific filters from individual request parameters
     */
    protected function addShopifySpecificFilters(array &$params)
    {
        // Allow direct Shopify parameters for advanced filtering
        $shopifyParams = [
            'status', 'financial_status', 'fulfillment_status',
            'created_at_min', 'created_at_max', 'updated_at_min', 'updated_at_max',
            'processed_at_min', 'processed_at_max', 'since_id'
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
                    'summary'     => 'Retrieve Shopify orders',
                    'description' => 'Get a list of orders from your Shopify store. Supports filtering with limit, offset, fields, and filter parameters. Filters are passed directly to Shopify for efficient server-side filtering. For better performance with large order lists, use the fields parameter to limit returned data. Large fields like line_items, addresses, and shipping details are excluded by default from lists but included when specifically requested.',
                    'operationId' => 'get' . $capitalized . 'Orders',
                    'parameters'  => [
                        [
                            'name'        => 'limit',
                            'in'          => 'query',
                            'schema'      => ['type' => 'integer'],
                            'description' => 'Maximum number of orders to return (default: 50)'
                        ],
                        [
                            'name'        => 'offset', 
                            'in'          => 'query',
                            'schema'      => ['type' => 'integer'],
                            'description' => 'Number of orders to skip (default: 0)'
                        ],
                        [
                            'name'        => 'fields',
                            'in'          => 'query', 
                            'schema'      => ['type' => 'string'],
                            'description' => 'Comma-separated list of fields to return (e.g., "id,name,total_price")'
                        ],
                        [
                            'name'        => 'filter',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'SQL-like filter expression (e.g., "financial_status=\'paid\' AND created_at >= \'2023-01-01\'"). Supported fields: financial_status, fulfillment_status, status, created_at, updated_at, processed_at'
                        ],
                        [
                            'name'        => 'ids',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Comma-separated list of order IDs to retrieve'
                        ],
                        [
                            'name'        => 'status',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by order status: open, closed, cancelled, any (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'financial_status',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by financial status: pending, authorized, partially_paid, paid, partially_refunded, refunded, voided (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'fulfillment_status',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by fulfillment status: shipped, partial, unshipped, any, unfulfilled (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'created_at_min',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter orders created after this date (ISO 8601 format)'
                        ],
                        [
                            'name'        => 'created_at_max',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter orders created before this date (ISO 8601 format)'
                        ]
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Orders retrieved successfully',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'resource' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'id'               => ['type' => 'integer', 'description' => 'Order ID'],
                                                'order_number'     => ['type' => 'integer', 'description' => 'Order number'],
                                                'name'             => ['type' => 'string', 'description' => 'Order name (e.g., #1001)'],
                                                'email'            => ['type' => 'string', 'description' => 'Customer email'],
                                                'financial_status' => ['type' => 'string', 'description' => 'Financial status'],
                                                'fulfillment_status' => ['type' => 'string', 'description' => 'Fulfillment status'],
                                                'total_price'      => ['type' => 'string', 'description' => 'Total order price'],
                                                'created_at'       => ['type' => 'string', 'description' => 'Creation date'],
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
                    'summary'     => 'Retrieve a specific Shopify order',
                    'description' => 'Get detailed information about a single order by its ID, including line items, addresses, and shipping details.',
                    'parameters'  => [
                        [
                            'name'        => 'id',
                            'in'          => 'path',
                            'required'    => true,
                            'type'        => 'integer',
                            'description' => 'Order ID',
                        ],
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Order details',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'resource' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'id'               => ['type' => 'integer', 'description' => 'Order ID'],
                                                'name'             => ['type' => 'string', 'description' => 'Order name'],
                                                'email'            => ['type' => 'string', 'description' => 'Customer email'],
                                                'line_items'       => ['type' => 'array', 'description' => 'Order line items'],
                                                'billing_address'  => ['type' => 'object', 'description' => 'Billing address'],
                                                'shipping_address' => ['type' => 'object', 'description' => 'Shipping address'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Order not found'],
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