<?php

namespace DreamFactory\Core\Shopify\GraphQL;

class QueryBuilder
{
    /**
     * Build products query with dynamic field selection
     */
    public static function buildProductsQuery($limit = 50, $fields = null, $cursor = null, $filters = [])
    {
        $fieldsSelection = self::buildProductFieldsSelection($fields);
        $queryArgs = self::buildQueryArgs($limit, $cursor, $filters);
        
        return "
            query getProducts {
                products($queryArgs) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    edges {
                        cursor
                        node {
                            $fieldsSelection
                        }
                    }
                }
            }
        ";
    }

    /**
     * Build orders query with dynamic field selection
     */
    public static function buildOrdersQuery($limit = 50, $fields = null, $cursor = null, $filters = [])
    {
        $fieldsSelection = self::buildOrderFieldsSelection($fields);
        $queryArgs = self::buildQueryArgs($limit, $cursor, $filters);
        
        return "
            query getOrders {
                orders($queryArgs) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    edges {
                        cursor
                        node {
                            $fieldsSelection
                        }
                    }
                }
            }
        ";
    }

    /**
     * Build single order query with dynamic field selection
     */
    public static function buildOrderQuery($graphqlId, $fields = null)
    {
        $fieldsSelection = self::buildOrderFieldsSelection($fields);
        
        return "
            query getOrder {
                order(id: \"$graphqlId\") {
                    $fieldsSelection
                }
            }
        ";
    }

    /**
     * Build customers query with dynamic field selection
     */
    public static function buildCustomersQuery($limit = 50, $fields = null, $cursor = null, $filters = [])
    {
        $fieldsSelection = self::buildCustomerFieldsSelection($fields);
        $queryArgs = self::buildQueryArgs($limit, $cursor, $filters);
        
        return "
            query getCustomers {
                customers($queryArgs) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    edges {
                        cursor
                        node {
                            $fieldsSelection
                        }
                    }
                }
            }
        ";
    }

    /**
     * Build single customer query with dynamic field selection
     */
    public static function buildCustomerQuery($graphqlId, $fields = null)
    {
        $fieldsSelection = self::buildCustomerFieldsSelection($fields);
        
        return "
            query getCustomer {
                customer(id: \"$graphqlId\") {
                    $fieldsSelection
                }
            }
        ";
    }

    /**
     * Build collections query with dynamic field selection
     */
    public static function buildCollectionsQuery($limit = 50, $fields = null, $cursor = null, $filters = [])
    {
        $fieldsSelection = self::buildCollectionFieldsSelection($fields);
        $queryArgs = self::buildQueryArgs($limit, $cursor, $filters);
        
        return "
            query getCollections {
                collections($queryArgs) {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    edges {
                        cursor
                        node {
                            $fieldsSelection
                        }
                    }
                }
            }
        ";
    }

    /**
     * Build single collection query with dynamic field selection
     */
    public static function buildCollectionQuery($graphqlId, $fields = null)
    {
        $fieldsSelection = self::buildCollectionFieldsSelection($fields);
        
        return "
            query getCollection {
                collection(id: \"$graphqlId\") {
                    $fieldsSelection
                }
            }
        ";
    }

    /**
     * Build collection products query
     */
    public static function buildCollectionProductsQuery($graphqlId, $limit = 50, $fields = null, $cursor = null)
    {
        $productFieldsSelection = self::buildProductFieldsSelection($fields);
        $queryArgs = self::buildQueryArgs($limit, $cursor, []);
        
        return "
            query getCollectionProducts {
                collection(id: \"$graphqlId\") {
                    products($queryArgs) {
                        pageInfo {
                            hasNextPage
                            hasPreviousPage
                            startCursor
                            endCursor
                        }
                        edges {
                            cursor
                            node {
                                $productFieldsSelection
                            }
                        }
                    }
                }
            }
        ";
    }

    /**
     * Build single product query
     */
    public static function buildProductQuery($graphqlId, $fields = null)
    {
        $fieldsSelection = self::buildProductFieldsSelection($fields, true);
        
        return "
            query getProduct {
                product(id: \"$graphqlId\") {
                    $fieldsSelection
                }
            }
        ";
    }

    /**
     * Build product fields selection based on requested fields
     */
    private static function buildProductFieldsSelection($fields = null, $includeVariants = false)
    {
        $defaultFields = [
            'id',
            'title',
            'handle',
            'vendor',
            'productType',
            'status',
            'createdAt',
            'updatedAt',
            'publishedAt',
            'tags'
        ];

        $largeFields = [
            'description',
            'descriptionHtml',
            'featuredImage { id url altText }',
            'images(first: 10) { edges { node { id url altText } } }',
            'options { id name values }'
        ];

        $variantFields = [
            'variants(first: 100) { 
                edges { 
                    node { 
                        id 
                        title 
                        price 
                        sku 
                        inventoryQuantity 
                        selectedOptions { name value }
                    } 
                } 
            }'
        ];

        if ($fields) {
            $requestedFields = array_map('trim', explode(',', $fields));
            $selectedFields = array_intersect($defaultFields, $requestedFields);
            
            // Check for large fields
            if (array_intersect(['description', 'images', 'options'], $requestedFields)) {
                $selectedFields = array_merge($selectedFields, $largeFields);
            }
            
            // Check for variants
            if (in_array('variants', $requestedFields) || $includeVariants) {
                $selectedFields = array_merge($selectedFields, $variantFields);
            }
        } else {
            // Default lightweight response
            $selectedFields = $defaultFields;
            if ($includeVariants) {
                $selectedFields = array_merge($selectedFields, $largeFields, $variantFields);
            }
        }

        return implode("\n            ", $selectedFields);
    }

    /**
     * Build order fields selection
     */
    private static function buildOrderFieldsSelection($fields = null)
    {
        $defaultFields = [
            'id',
            'name',
            'email',
            'phone',
            'createdAt',
            'updatedAt',
            'processedAt',
            'displayFinancialStatus',
            'displayFulfillmentStatus',
            'confirmed',
            'totalPrice',
            'subtotalPrice',
            'totalTax',
            'currencyCode',
            'customer { id email firstName lastName }',
            'tags'
        ];

        $largeFields = [
            'lineItems(first: 100) { 
                edges { 
                    node { 
                        id 
                        title 
                        quantity 
                        price 
                        product { id title }
                        variant { id title sku }
                    } 
                } 
            }',
            'billingAddress { address1 address2 city province country zip }',
            'shippingAddress { address1 address2 city province country zip }'
        ];

        if ($fields) {
            $requestedFields = array_map('trim', explode(',', $fields));
            $selectedFields = array_intersect($defaultFields, $requestedFields);
            
            if (array_intersect(['lineItems', 'line_items', 'addresses'], $requestedFields)) {
                $selectedFields = array_merge($selectedFields, $largeFields);
            }
        } else {
            $selectedFields = $defaultFields;
        }

        return implode("\n            ", $selectedFields);
    }

    /**
     * Build customer fields selection
     */
    private static function buildCustomerFieldsSelection($fields = null)
    {
        $defaultFields = [
            'id',
            'email',
            'firstName',
            'lastName',
            'phone',
            'createdAt',
            'updatedAt',
            'verifiedEmail',
            'state',
            'tags'
        ];

        $largeFields = [
            'addresses(first: 10) { address1 address2 city province country zip }',
            'defaultAddress { address1 address2 city province country zip }'
        ];

        if ($fields) {
            $requestedFields = array_map('trim', explode(',', $fields));
            $selectedFields = array_intersect($defaultFields, $requestedFields);
            
            if (array_intersect(['addresses', 'defaultAddress'], $requestedFields)) {
                $selectedFields = array_merge($selectedFields, $largeFields);
            }
        } else {
            $selectedFields = $defaultFields;
        }

        return implode("\n            ", $selectedFields);
    }

    /**
     * Build collection fields selection
     */
    private static function buildCollectionFieldsSelection($fields = null)
    {
        $defaultFields = [
            'id',
            'title',
            'handle',
            'description',
            'updatedAt'
        ];

        if ($fields) {
            $requestedFields = array_map('trim', explode(',', $fields));
            $selectedFields = array_intersect($defaultFields, $requestedFields);
        } else {
            $selectedFields = $defaultFields;
        }

        return implode("\n            ", $selectedFields);
    }

    /**
     * Build query arguments string
     */
    private static function buildQueryArgs($limit, $cursor = null, $filters = [])
    {
        $args = [];
        
        if ($limit) {
            $args[] = "first: $limit";
        }
        
        if ($cursor) {
            $args[] = "after: \"$cursor\"";
        }

        // Add filters as query string
        if (!empty($filters)) {
            $queryString = self::buildFilterQuery($filters);
            if ($queryString) {
                $args[] = "query: \"$queryString\"";
            }
        }

        return implode(', ', $args);
    }

    /**
     * Build filter query string for Shopify GraphQL
     */
    private static function buildFilterQuery($filters)
    {
        $queryParts = [];
        
        foreach ($filters as $field => $value) {
            switch ($field) {
                case 'vendor':
                    $queryParts[] = "vendor:$value";
                    break;
                case 'product_type':
                    $queryParts[] = "product_type:$value";
                    break;
                case 'status':
                    $queryParts[] = "status:$value";
                    break;
                case 'financial_status':
                    $queryParts[] = "financial_status:$value";
                    break;
                case 'fulfillment_status':
                    $queryParts[] = "fulfillment_status:$value";
                    break;
                default:
                    // Handle generic filters
                    if (is_string($value)) {
                        $queryParts[] = "$field:$value";
                    }
                    break;
            }
        }
        
        return implode(' AND ', $queryParts);
    }
} 