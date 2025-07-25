<?php

namespace DreamFactory\Core\Shopify\Utilities;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ShopifyCache
{
    /**
     * Default cache TTL in seconds (5 minutes)
     */
    const DEFAULT_TTL = 300;

    /**
     * Cache TTL configurations for different resource types
     */
    const CACHE_TTLS = [
        'products' => 600,      // 10 minutes - products change less frequently
        'orders' => 120,        // 2 minutes - orders are more dynamic
        'customers' => 300,     // 5 minutes - customers change moderately
        'collections' => 1800,  // 30 minutes - collections are relatively static
        'single' => 300,        // 5 minutes - single resource queries
        'variants' => 600,      // 10 minutes - variants are relatively stable
    ];

    /**
     * Check if caching is enabled
     */
    public static function isEnabled(): bool
    {
        return Config::get('shopify.caching.enabled', true);
    }

    /**
     * Generate cache key for a request
     */
    public static function generateKey(string $shopDomain, string $operation, array $parameters = []): string
    {
        // Sort parameters for consistent cache keys
        ksort($parameters);
        
        // Remove sensitive parameters
        $sanitizedParams = self::sanitizeParameters($parameters);
        
        // Create hash of parameters
        $paramHash = md5(json_encode($sanitizedParams));
        
        return "shopify:{$shopDomain}:{$operation}:{$paramHash}";
    }

    /**
     * Get cached response
     */
    public static function get(string $shopDomain, string $operation, array $parameters = []): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        $cacheKey = self::generateKey($shopDomain, $operation, $parameters);
        $cached = Cache::get($cacheKey);

        ShopifyLogger::logCache($operation, $cacheKey, $cached !== null);

        return $cached;
    }

    /**
     * Cache response with appropriate TTL
     */
    public static function put(string $shopDomain, string $operation, array $parameters, array $response): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $cacheKey = self::generateKey($shopDomain, $operation, $parameters);
        $ttl = self::getTTL($operation);

        // Add cache metadata
        $cachedData = [
            'data' => $response,
            'cached_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($ttl)->toISOString(),
            'operation' => $operation,
            'shop_domain' => $shopDomain
        ];

        Cache::put($cacheKey, $cachedData, $ttl);

        ShopifyLogger::logCache($operation, $cacheKey, false, $ttl);
    }

    /**
     * Invalidate cache for specific operation
     */
    public static function invalidate(string $shopDomain, string $operation, array $parameters = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $cacheKey = self::generateKey($shopDomain, $operation, $parameters);
        Cache::forget($cacheKey);

        ShopifyLogger::logCache($operation . '_invalidate', $cacheKey, false);
    }

    /**
     * Invalidate all cache for a shop
     */
    public static function invalidateShop(string $shopDomain): void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Use cache tags if available, otherwise clear by pattern
        $pattern = "shopify:{$shopDomain}:*";
        
        // This is a simplified approach - in production you might want to use Redis with SCAN
        // or implement proper cache tagging
        Cache::flush(); // Note: This clears all cache - implement pattern matching for production
        
        ShopifyLogger::logCache('shop_invalidate', $pattern, false);
    }

    /**
     * Invalidate cache based on resource type and related resources
     */
    public static function invalidateRelated(string $shopDomain, string $resourceType, $resourceId = null): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $invalidationMap = [
            'product' => ['products', 'collections'],  // Product changes affect collections
            'order' => ['orders', 'customers'],        // Order changes affect customer data
            'customer' => ['customers'],
            'collection' => ['collections', 'products'] // Collection changes might affect product lists
        ];

        $resourcesToInvalidate = $invalidationMap[$resourceType] ?? [$resourceType];

        foreach ($resourcesToInvalidate as $resource) {
            // Invalidate list queries
            self::invalidatePattern($shopDomain, $resource);
            
            // Invalidate specific resource if ID provided
            if ($resourceId) {
                self::invalidatePattern($shopDomain, $resource . '_' . $resourceId);
            }
        }
    }

    /**
     * Get appropriate TTL for operation
     */
    private static function getTTL(string $operation): int
    {
        // Extract resource type from operation
        foreach (self::CACHE_TTLS as $resource => $ttl) {
            if (strpos($operation, $resource) !== false) {
                return $ttl;
            }
        }

        return self::DEFAULT_TTL;
    }

    /**
     * Remove sensitive parameters from cache key generation
     */
    private static function sanitizeParameters(array $parameters): array
    {
        $sensitiveKeys = ['access_token', 'api_key', 'api_secret', 'password'];
        
        return array_filter($parameters, function($key) use ($sensitiveKeys) {
            return !in_array(strtolower($key), $sensitiveKeys);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Invalidate cache by pattern (simplified implementation)
     */
    private static function invalidatePattern(string $shopDomain, string $pattern): void
    {
        // In a production environment, you'd want to implement proper pattern matching
        // This is a simplified version
        $cachePrefix = "shopify:{$shopDomain}:{$pattern}";
        
        // For now, we'll just log the invalidation
        // In production, implement proper pattern-based cache clearing
        ShopifyLogger::logCache('pattern_invalidate', $cachePrefix, false);
    }

    /**
     * Get cache statistics
     */
    public static function getStats(string $shopDomain): array
    {
        // This would require implementing cache statistics tracking
        // For now, return basic info
        return [
            'enabled' => self::isEnabled(),
            'shop_domain' => $shopDomain,
            'default_ttl' => self::DEFAULT_TTL,
            'ttl_config' => self::CACHE_TTLS
        ];
    }

    /**
     * Warm up cache for common queries
     */
    public static function warmUp(string $shopDomain, array $commonQueries = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Default common queries to warm up
        $defaultQueries = [
            ['operation' => 'products', 'parameters' => ['limit' => 50]],
            ['operation' => 'collections', 'parameters' => ['limit' => 20]],
            ['operation' => 'orders', 'parameters' => ['limit' => 20]]
        ];

        $queriesToWarm = array_merge($defaultQueries, $commonQueries);

        foreach ($queriesToWarm as $query) {
            $cacheKey = self::generateKey($shopDomain, $query['operation'], $query['parameters']);
            
            // Only warm if not already cached
            if (!Cache::has($cacheKey)) {
                ShopifyLogger::logCache('cache_warmup', $cacheKey, false);
                // Note: Actual query execution would happen in the calling service
            }
        }
    }

    /**
     * Check if cached data is fresh enough for the request
     */
    public static function isFresh(array $cachedData, int $maxAge = null): bool
    {
        if (!isset($cachedData['cached_at'])) {
            return false;
        }

        $cachedAt = new \DateTime($cachedData['cached_at']);
        $now = new \DateTime();
        $age = $now->getTimestamp() - $cachedAt->getTimestamp();

        if ($maxAge !== null) {
            return $age <= $maxAge;
        }

        // Use operation-specific TTL
        $operation = $cachedData['operation'] ?? 'default';
        $ttl = self::getTTL($operation);

        return $age <= $ttl;
    }
} 