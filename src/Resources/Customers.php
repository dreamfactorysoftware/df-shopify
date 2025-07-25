<?php

namespace DreamFactory\Core\Shopify\Resources;

use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Shopify\GraphQL\QueryBuilder;
use DreamFactory\Core\Shopify\GraphQL\ResponseTransformer;
use Illuminate\Support\Arr;

class Customers extends BaseRestResource
{
    const RESOURCE_NAME = 'customers';

    /**
     * Handle GET requests for customers using GraphQL
     * 
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
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
            
            // Route to appropriate handler
            if ($resourceId) {
                // GET /customers/{id}
                return $this->getSingleCustomer($resourceId, $shopDomain, $accessToken, $fields);
            } else {
                // GET /customers
                return $this->getCustomers($shopDomain, $accessToken, $limit, $offset, $fields, $filter, $ids);
            }

        } catch (\Exception $e) {
            \Log::error('Customers API error: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to fetch customers: ' . $e->getMessage());
        }
    }

    /**
     * Get customers list using GraphQL
     */
    private function getCustomers($shopDomain, $accessToken, $limit, $offset, $fields, $filter, $ids)
    {
        // Build filters from DreamFactory parameters
        $filters = [];
        if ($filter) {
            $filters = $this->parseFilters($filter);
        }
        if ($ids) {
            $filters['ids'] = explode(',', $ids);
        }
        
        // Calculate cursor from offset (simplified approach)
        $cursor = $offset > 0 ? base64_encode("arrayconnection:$offset") : null;
        
        // Build GraphQL query
        $query = QueryBuilder::buildCustomersQuery($limit, $fields, $cursor, $filters);
        
        \Log::info('Executing GraphQL customers query', ['query' => $query]);
        
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
            throw new InternalServerErrorException('Shopify GraphQL API call failed: ' . $curlError);
        }
        
        $responseData = json_decode($response, true);
        
        // Check for GraphQL errors
        if (isset($responseData['errors'])) {
            $errorMessage = 'GraphQL errors: ' . json_encode($responseData['errors']);
            \Log::error($errorMessage);
            throw new BadRequestException($errorMessage);
        }
        
        // Transform GraphQL response to REST format
        return ResponseTransformer::transformCustomersResponse($responseData);
    }

    /**
     * Get single customer using GraphQL
     */
    private function getSingleCustomer($customerId, $shopDomain, $accessToken, $fields)
    {
        // Convert numeric ID to GraphQL global ID
        $graphqlId = "gid://shopify/Customer/$customerId";
        
        // Build GraphQL query
        $query = QueryBuilder::buildCustomerQuery($graphqlId, $fields);
        
        \Log::info('Executing GraphQL single customer query', [
            'query' => $query, 
            'customer_id' => $customerId
        ]);
        
        // Use cURL for GraphQL
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
            throw new InternalServerErrorException('Shopify GraphQL API call failed: ' . $curlError);
        }
        
        $responseData = json_decode($response, true);
        
        // Check for GraphQL errors
        if (isset($responseData['errors'])) {
            $errorMessage = 'GraphQL errors: ' . json_encode($responseData['errors']);
            \Log::error($errorMessage);
            throw new BadRequestException($errorMessage);
        }
        
        // Transform GraphQL response to REST format
        return ResponseTransformer::transformSingleCustomerResponse($responseData);
    }

    /**
     * Parse DreamFactory filter string into Shopify GraphQL filters
     */
    private function parseFilters($filter)
    {
        $filters = [];
        // Simple parsing - in production you'd want more robust parsing
        if (strpos($filter, 'state') !== false) {
            if (preg_match("/state\s*=\s*['\"]([^'\"]+)['\"]/",$filter, $matches)) {
                $filters['state'] = $matches[1];
            }
        }
        return $filters;
    }

    /**
     * Get a specific customer by ID
     * 
     * @param string $customerId
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    protected function getCustomerById($customerId)
    {
        try {
            $shopifyService = $this->getService();
            $shopDomain = $shopifyService->getShopDomain();
            $accessToken = $shopifyService->getAccessToken();
            $apiVersion = $shopifyService->getApiVersion();
            
            $url = "https://{$shopDomain}/admin/api/{$apiVersion}/customers/{$customerId}.json";
            

            
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
                throw new BadRequestException("Customer with ID {$customerId} not found.");
            }
            
            if ($httpCode !== 200) {
                $responseBody = json_decode($response, true);
                $errorMsg = $responseBody['errors'] ?? "HTTP {$httpCode}";
                throw new InternalServerErrorException("Failed to fetch customer: {$errorMsg}");
            }
            
            $responseBody = json_decode($response, true);
            $customer = $responseBody['customer'] ?? null;
            
            if (!$customer) {
                throw new BadRequestException("Customer with ID {$customerId} not found.");
            }
            
            // Transform single customer to our format (include all fields for individual customer requests)
            $transformedCustomer = $this->transformCustomer($customer, true);
            
            return ['resource' => [$transformedCustomer]];
            
        } catch (\Exception $e) {
            \Log::error('Error getting Shopify customer: ' . $e->getMessage());
            throw new InternalServerErrorException('Failed to fetch customer: ' . $e->getMessage());
        }
    }

    /**
     * Block all non-GET methods for read-only access
     */
    protected function handlePOST()
    {
        throw new BadRequestException('Creating customers is not supported in read-only mode.');
    }

    protected function handlePUT()
    {
        throw new BadRequestException('Updating customers is not supported in read-only mode.');
    }

    protected function handlePATCH()
    {
        throw new BadRequestException('Updating customers is not supported in read-only mode.');
    }

    protected function handleDELETE()
    {
        throw new BadRequestException('Deleting customers is not supported in read-only mode.');
    }

    /**
     * Transform a single Shopify customer to our format
     */
    protected function transformCustomer($customer, $includeLargeFields = false)
    {
        // Start with essential fields (lightweight)
        $transformed = [
            'id' => $customer['id'],
            'email' => $customer['email'] ?? null,
            'first_name' => $customer['first_name'] ?? null,
            'last_name' => $customer['last_name'] ?? null,
            'phone' => $customer['phone'] ?? null,
            'created_at' => $customer['created_at'] ?? null,
            'updated_at' => $customer['updated_at'] ?? null,
            'orders_count' => $customer['orders_count'] ?? 0,
            'total_spent' => $customer['total_spent'] ?? '0.00',
            'verified_email' => $customer['verified_email'] ?? false,
            'state' => $customer['state'] ?? null,
            'tags' => $customer['tags'] ?? '',
            'currency' => $customer['currency'] ?? null,
            'accepts_marketing' => $customer['accepts_marketing'] ?? false,
            'accepts_marketing_updated_at' => $customer['accepts_marketing_updated_at'] ?? null,
            'marketing_opt_in_level' => $customer['marketing_opt_in_level'] ?? null,
            'tax_exempt' => $customer['tax_exempt'] ?? false,
        ];
        
        // Add large fields only if requested or if this is a single customer request
        if ($includeLargeFields) {
            $transformed['addresses'] = $customer['addresses'] ?? [];
            $transformed['default_address'] = $customer['default_address'] ?? null;
            $transformed['last_order_id'] = $customer['last_order_id'] ?? null;
            $transformed['last_order_name'] = $customer['last_order_name'] ?? null;
            $transformed['note'] = $customer['note'] ?? null;
            $transformed['tax_exemptions'] = $customer['tax_exemptions'] ?? [];
            $transformed['email_marketing_consent'] = $customer['email_marketing_consent'] ?? null;
            $transformed['sms_marketing_consent'] = $customer['sms_marketing_consent'] ?? null;
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
     * Supports syntax like: "email='test@example.com' AND state='enabled'"
     */
    protected function parseFilterString(array &$params, $filter)
    {
        // Handle email filter
        if (preg_match("/email\s*[=~]\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['email'] = $matches[1];
        }
        
        // Handle state filter (enabled, disabled, invited, declined)
        if (preg_match("/state\s*=\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['state'] = $matches[1];
        }
        
        // Handle phone filter
        if (preg_match("/phone\s*[=~]\s*['\"]([^'\"]+)['\"]/i", $filter, $matches)) {
            $params['phone'] = $matches[1];
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
        

    }

    /**
     * Add Shopify-specific filters from individual request parameters
     */
    protected function addShopifySpecificFilters(array &$params)
    {
        // Allow direct Shopify parameters for advanced filtering
        $shopifyParams = [
            'email', 'phone', 'state', 
            'created_at_min', 'created_at_max', 'updated_at_min', 'updated_at_max',
            'since_id'
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
                    'summary'     => 'Retrieve Shopify customers',
                    'description' => 'Get a list of customers from your Shopify store. Supports filtering with limit, offset, fields, and filter parameters. Filters are passed directly to Shopify for efficient server-side filtering. For better performance with large customer lists, use the fields parameter to limit returned data. Large fields like addresses and consent details are excluded by default from lists but included when specifically requested.',
                    'operationId' => 'get' . $capitalized . 'Customers',
                    'parameters'  => [
                        [
                            'name'        => 'limit',
                            'in'          => 'query',
                            'schema'      => ['type' => 'integer'],
                            'description' => 'Maximum number of customers to return (default: 50)'
                        ],
                        [
                            'name'        => 'offset', 
                            'in'          => 'query',
                            'schema'      => ['type' => 'integer'],
                            'description' => 'Number of customers to skip (default: 0)'
                        ],
                        [
                            'name'        => 'fields',
                            'in'          => 'query', 
                            'schema'      => ['type' => 'string'],
                            'description' => 'Comma-separated list of fields to return (e.g., "id,email,first_name,last_name")'
                        ],
                        [
                            'name'        => 'filter',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'SQL-like filter expression (e.g., "state=\'enabled\' AND created_at >= \'2023-01-01\'"). Supported fields: email, phone, state, created_at, updated_at'
                        ],
                        [
                            'name'        => 'ids',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Comma-separated list of customer IDs to retrieve'
                        ],
                        [
                            'name'        => 'email',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by customer email address (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'phone',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by customer phone number (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'state',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter by customer state: enabled, disabled, invited, declined (Shopify-specific parameter)'
                        ],
                        [
                            'name'        => 'created_at_min',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter customers created after this date (ISO 8601 format)'
                        ],
                        [
                            'name'        => 'created_at_max',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Filter customers created before this date (ISO 8601 format)'
                        ]
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Customers retrieved successfully',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'resource' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'id'               => ['type' => 'integer', 'description' => 'Customer ID'],
                                                'email'            => ['type' => 'string', 'description' => 'Customer email'],
                                                'first_name'       => ['type' => 'string', 'description' => 'First name'],
                                                'last_name'        => ['type' => 'string', 'description' => 'Last name'],
                                                'phone'            => ['type' => 'string', 'description' => 'Phone number'],
                                                'orders_count'     => ['type' => 'integer', 'description' => 'Number of orders'],
                                                'total_spent'      => ['type' => 'string', 'description' => 'Total amount spent'],
                                                'state'            => ['type' => 'string', 'description' => 'Customer state'],
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
                    'summary'     => 'Retrieve a specific Shopify customer',
                    'description' => 'Get detailed information about a single customer by their ID, including addresses and marketing consent details.',
                    'parameters'  => [
                        [
                            'name'        => 'id',
                            'in'          => 'path',
                            'required'    => true,
                            'type'        => 'integer',
                            'description' => 'Customer ID',
                        ],
                    ],
                    'responses'   => [
                        '200' => [
                            'description' => 'Customer details',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'resource' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'id'               => ['type' => 'integer', 'description' => 'Customer ID'],
                                                'email'            => ['type' => 'string', 'description' => 'Customer email'],
                                                'first_name'       => ['type' => 'string', 'description' => 'First name'],
                                                'last_name'        => ['type' => 'string', 'description' => 'Last name'],
                                                'addresses'        => ['type' => 'array', 'description' => 'Customer addresses'],
                                                'default_address'  => ['type' => 'object', 'description' => 'Default address'],
                                                'orders_count'     => ['type' => 'integer', 'description' => 'Number of orders'],
                                                'total_spent'      => ['type' => 'string', 'description' => 'Total amount spent'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Customer not found'],
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