<?php

namespace DreamFactory\Core\Shopify\GraphQL;

class ResponseTransformer
{
    /**
     * Transform GraphQL products response to REST format
     */
    public static function transformProductsResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['products']['edges'])) {
            return ['resource' => []];
        }

        $products = [];
        foreach ($graphqlResponse['data']['products']['edges'] as $edge) {
            $products[] = self::transformProduct($edge['node']);
        }

        return [
            'resource' => $products,
            'meta' => self::extractPageInfo($graphqlResponse['data']['products']['pageInfo'] ?? [])
        ];
    }

    /**
     * Transform GraphQL orders response to REST format
     */
    public static function transformOrdersResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['orders']['edges'])) {
            return ['resource' => []];
        }

        $orders = [];
        foreach ($graphqlResponse['data']['orders']['edges'] as $edge) {
            $orders[] = self::transformOrder($edge['node']);
        }

        return [
            'resource' => $orders,
            'meta' => self::extractPageInfo($graphqlResponse['data']['orders']['pageInfo'] ?? [])
        ];
    }

    /**
     * Transform GraphQL customers response to REST format
     */
    public static function transformCustomersResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['customers']['edges'])) {
            return ['resource' => []];
        }

        $customers = [];
        foreach ($graphqlResponse['data']['customers']['edges'] as $edge) {
            $customers[] = self::transformCustomer($edge['node']);
        }

        return [
            'resource' => $customers,
            'meta' => self::extractPageInfo($graphqlResponse['data']['customers']['pageInfo'] ?? [])
        ];
    }

    /**
     * Transform GraphQL collections response to REST format
     */
    public static function transformCollectionsResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['collections']['edges'])) {
            return ['resource' => []];
        }

        $collections = [];
        foreach ($graphqlResponse['data']['collections']['edges'] as $edge) {
            $collections[] = self::transformCollection($edge['node']);
        }

        return [
            'resource' => $collections,
            'meta' => self::extractPageInfo($graphqlResponse['data']['collections']['pageInfo'] ?? [])
        ];
    }

    /**
     * Transform single product from GraphQL to REST format
     */
    public static function transformSingleProductResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['product'])) {
            return ['resource' => []];
        }

        return [
            'resource' => [self::transformProduct($graphqlResponse['data']['product'])]
        ];
    }

    /**
     * Transform GraphQL single order response to REST format
     */
    public static function transformSingleOrderResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['order'])) {
            return ['resource' => []];
        }

        return [
            'resource' => [self::transformOrder($graphqlResponse['data']['order'])]
        ];
    }

    /**
     * Transform GraphQL single customer response to REST format
     */
    public static function transformSingleCustomerResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['customer'])) {
            return ['resource' => []];
        }

        return [
            'resource' => [self::transformCustomer($graphqlResponse['data']['customer'])]
        ];
    }

    /**
     * Transform GraphQL single collection response to REST format
     */
    public static function transformSingleCollectionResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['collection'])) {
            return ['resource' => []];
        }

        return [
            'resource' => [self::transformCollection($graphqlResponse['data']['collection'])]
        ];
    }

    /**
     * Transform GraphQL collection products response to REST format
     */
    public static function transformCollectionProductsResponse($graphqlResponse)
    {
        if (!isset($graphqlResponse['data']['collection']['products']['edges'])) {
            return ['resource' => []];
        }

        $products = [];
        foreach ($graphqlResponse['data']['collection']['products']['edges'] as $edge) {
            $products[] = self::transformProduct($edge['node']);
        }

        return [
            'resource' => $products,
            'meta' => self::extractPageInfo($graphqlResponse['data']['collection']['products']['pageInfo'] ?? [])
        ];
    }

    /**
     * Transform a single product node
     */
    private static function transformProduct($product)
    {
        $transformed = [
            'id' => self::extractNumericId($product['id'] ?? ''),
            'title' => $product['title'] ?? '',
            'handle' => $product['handle'] ?? '',
            'vendor' => $product['vendor'] ?? '',
            'product_type' => $product['productType'] ?? '',
            'status' => strtolower($product['status'] ?? ''),
            'created_at' => $product['createdAt'] ?? '',
            'updated_at' => $product['updatedAt'] ?? '',
            'published_at' => $product['publishedAt'] ?? '',
            'tags' => implode(', ', $product['tags'] ?? [])
        ];

        // Add description if present
        if (isset($product['description'])) {
            $transformed['description'] = $product['description'];
        }
        if (isset($product['descriptionHtml'])) {
            $transformed['body_html'] = $product['descriptionHtml'];
        }

        // Add images if present
        if (isset($product['images']['edges'])) {
            $transformed['images'] = [];
            foreach ($product['images']['edges'] as $edge) {
                $transformed['images'][] = [
                    'id' => self::extractNumericId($edge['node']['id'] ?? ''),
                    'src' => $edge['node']['url'] ?? '',
                    'alt' => $edge['node']['altText'] ?? ''
                ];
            }
            $transformed['images_count'] = count($transformed['images']);
        }

        // Add featured image if present
        if (isset($product['featuredImage'])) {
            $transformed['image'] = [
                'id' => self::extractNumericId($product['featuredImage']['id'] ?? ''),
                'src' => $product['featuredImage']['url'] ?? '',
                'alt' => $product['featuredImage']['altText'] ?? ''
            ];
        }

        // Add variants if present
        if (isset($product['variants']['edges'])) {
            $transformed['variants'] = [];
            foreach ($product['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                $transformed['variants'][] = [
                    'id' => self::extractNumericId($variant['id'] ?? ''),
                    'product_id' => $transformed['id'],
                    'title' => $variant['title'] ?? '',
                    'price' => $variant['price'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'inventory_quantity' => $variant['inventoryQuantity'] ?? 0,
                    'option1' => $variant['selectedOptions'][0]['value'] ?? null,
                    'option2' => $variant['selectedOptions'][1]['value'] ?? null,
                    'option3' => $variant['selectedOptions'][2]['value'] ?? null
                ];
            }
            $transformed['variants_count'] = count($transformed['variants']);
        }

        // Add options if present
        if (isset($product['options'])) {
            $transformed['options'] = [];
            foreach ($product['options'] as $option) {
                $transformed['options'][] = [
                    'id' => self::extractNumericId($option['id'] ?? ''),
                    'name' => $option['name'] ?? '',
                    'values' => $option['values'] ?? []
                ];
            }
        }

        return $transformed;
    }

    /**
     * Transform a single order node
     */
    private static function transformOrder($order)
    {
        $transformed = [
            'id' => self::extractNumericId($order['id'] ?? ''),
            'name' => $order['name'] ?? '',
            'order_number' => $order['name'] ?? '',  // name IS the order number in GraphQL
            'email' => $order['email'] ?? '',
            'phone' => $order['phone'] ?? '',
            'created_at' => $order['createdAt'] ?? '',
            'updated_at' => $order['updatedAt'] ?? '',
            'processed_at' => $order['processedAt'] ?? '',
            'financial_status' => strtolower($order['displayFinancialStatus'] ?? ''),
            'fulfillment_status' => strtolower($order['displayFulfillmentStatus'] ?? ''),
            'confirmed' => $order['confirmed'] ?? false,
            'total_price' => $order['totalPrice'] ?? '0.00',
            'subtotal_price' => $order['subtotalPrice'] ?? '0.00',
            'total_tax' => $order['totalTax'] ?? '0.00',
            'currency' => $order['currencyCode'] ?? 'USD',
            'tags' => implode(', ', $order['tags'] ?? [])
        ];

        // Add customer info if present
        if (isset($order['customer'])) {
            $transformed['customer_id'] = self::extractNumericId($order['customer']['id'] ?? '');
            $transformed['customer'] = [
                'id' => $transformed['customer_id'],
                'email' => $order['customer']['email'] ?? '',
                'first_name' => $order['customer']['firstName'] ?? '',
                'last_name' => $order['customer']['lastName'] ?? ''
            ];
        }

        // Add line items if present
        if (isset($order['lineItems']['edges'])) {
            $transformed['line_items'] = [];
            foreach ($order['lineItems']['edges'] as $edge) {
                $item = $edge['node'];
                $transformed['line_items'][] = [
                    'id' => self::extractNumericId($item['id'] ?? ''),
                    'title' => $item['title'] ?? '',
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => $item['price'] ?? '0.00',
                    'product_id' => self::extractNumericId($item['product']['id'] ?? ''),
                    'variant_id' => self::extractNumericId($item['variant']['id'] ?? ''),
                    'sku' => $item['variant']['sku'] ?? ''
                ];
            }
            $transformed['line_items_count'] = count($transformed['line_items']);
        }

        // Add addresses if present
        if (isset($order['billingAddress'])) {
            $transformed['billing_address'] = $order['billingAddress'];
        }
        if (isset($order['shippingAddress'])) {
            $transformed['shipping_address'] = $order['shippingAddress'];
        }

        return $transformed;
    }

    /**
     * Transform a single customer node
     */
    private static function transformCustomer($customer)
    {
        $transformed = [
            'id' => self::extractNumericId($customer['id'] ?? ''),
            'email' => $customer['email'] ?? '',
            'first_name' => $customer['firstName'] ?? '',
            'last_name' => $customer['lastName'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'created_at' => $customer['createdAt'] ?? '',
            'updated_at' => $customer['updatedAt'] ?? '',
            'verified_email' => $customer['verifiedEmail'] ?? false,
            'state' => strtolower($customer['state'] ?? ''),
            'tags' => implode(', ', $customer['tags'] ?? [])
        ];

        // Add addresses if present
        if (isset($customer['addresses'])) {
            $transformed['addresses'] = $customer['addresses'];
        }
        if (isset($customer['defaultAddress'])) {
            $transformed['default_address'] = $customer['defaultAddress'];
        }

        return $transformed;
    }

    /**
     * Transform a single collection node
     */
    private static function transformCollection($collection)
    {
        return [
            'id' => self::extractNumericId($collection['id'] ?? ''),
            'title' => $collection['title'] ?? '',
            'handle' => $collection['handle'] ?? '',
            'description' => $collection['description'] ?? '',
            'updated_at' => $collection['updatedAt'] ?? ''
        ];
    }

    /**
     * Extract numeric ID from Shopify's GraphQL ID format
     */
    public static function extractNumericId($graphqlId)
    {
        if (empty($graphqlId)) {
            return null;
        }
        
        // Shopify GraphQL IDs are in format "gid://shopify/Product/123456789"
        $parts = explode('/', $graphqlId);
        return end($parts);
    }

    /**
     * Extract pagination info from GraphQL pageInfo
     */
    private static function extractPageInfo($pageInfo)
    {
        return [
            'has_next_page' => $pageInfo['hasNextPage'] ?? false,
            'has_previous_page' => $pageInfo['hasPreviousPage'] ?? false,
            'start_cursor' => $pageInfo['startCursor'] ?? null,
            'end_cursor' => $pageInfo['endCursor'] ?? null
        ];
    }
} 