<?php

namespace DreamFactory\Core\Shopify\Utilities;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class ShopifyLogger
{
    /**
     * Debug mode setting
     */
    private static function isDebugMode(): bool
    {
        return Config::get('shopify.debug_mode', false);
    }

    /**
     * Log GraphQL query execution with performance metrics
     */
    public static function logGraphQLQuery(string $operation, string $query, array $variables = [], float $executionTime = null, string $shopDomain = null): void
    {
        $context = [
            'operation' => $operation,
            'shop_domain' => $shopDomain,
            'query_length' => strlen($query),
            'variables_count' => count($variables),
            'execution_time_ms' => $executionTime ? round($executionTime * 1000, 2) : null,
            'timestamp' => now()->toISOString()
        ];

        if (self::isDebugMode()) {
            $context['query'] = $query;
            $context['variables'] = self::sanitizeVariables($variables);
        }

        Log::info("GraphQL Query Executed: {$operation}", $context);
    }

    /**
     * Log GraphQL response with metrics
     */
    public static function logGraphQLResponse(string $operation, array $response, int $httpCode, float $executionTime = null): void
    {
        $context = [
            'operation' => $operation,
            'http_code' => $httpCode,
            'has_errors' => isset($response['errors']),
            'data_size' => isset($response['data']) ? count($response['data']) : 0,
            'execution_time_ms' => $executionTime ? round($executionTime * 1000, 2) : null,
            'timestamp' => now()->toISOString()
        ];

        if (isset($response['errors'])) {
            $context['error_count'] = count($response['errors']);
            Log::error("GraphQL Response Error: {$operation}", array_merge($context, [
                'errors' => $response['errors']
            ]));
        } else {
            Log::info("GraphQL Response Success: {$operation}", $context);
        }

        if (self::isDebugMode() && isset($response['data'])) {
            Log::debug("GraphQL Response Data: {$operation}", [
                'response_data' => $response['data']
            ]);
        }
    }

    /**
     * Log service initialization
     */
    public static function logServiceInit(string $shopDomain, string $apiVersion, array $config = []): void
    {
        $context = [
            'shop_domain' => $shopDomain,
            'api_version' => $apiVersion,
            'config_keys' => array_keys(self::sanitizeConfig($config)),
            'timestamp' => now()->toISOString()
        ];

        Log::info('Shopify Service Initialized', $context);
    }

    /**
     * Log authentication events
     */
    public static function logAuthentication(string $shopDomain, bool $success, string $method = 'access_token', array $details = []): void
    {
        $context = [
            'shop_domain' => $shopDomain,
            'auth_method' => $method,
            'success' => $success,
            'details' => self::sanitizeAuthDetails($details),
            'timestamp' => now()->toISOString()
        ];

        if ($success) {
            Log::info('Shopify Authentication Success', $context);
        } else {
            Log::error('Shopify Authentication Failed', $context);
        }
    }

    /**
     * Log rate limiting information
     */
    public static function logRateLimit(string $shopDomain, array $rateLimitHeaders): void
    {
        $context = [
            'shop_domain' => $shopDomain,
            'call_limit' => $rateLimitHeaders['X-Shopify-Shop-Api-Call-Limit'] ?? null,
            'calls_made' => null,
            'calls_remaining' => null,
            'timestamp' => now()->toISOString()
        ];

        // Parse call limit (e.g., "32/40" means 32 calls made out of 40 allowed)
        if (isset($rateLimitHeaders['X-Shopify-Shop-Api-Call-Limit'])) {
            $parts = explode('/', $rateLimitHeaders['X-Shopify-Shop-Api-Call-Limit']);
            if (count($parts) === 2) {
                $context['calls_made'] = (int)$parts[0];
                $context['calls_remaining'] = (int)$parts[1] - (int)$parts[0];
            }
        }

        if ($context['calls_remaining'] !== null && $context['calls_remaining'] < 5) {
            Log::warning('Shopify Rate Limit Warning', $context);
        } else {
            Log::debug('Shopify Rate Limit Status', $context);
        }
    }

    /**
     * Log caching events
     */
    public static function logCache(string $operation, string $cacheKey, bool $hit, int $ttl = null): void
    {
        $context = [
            'operation' => $operation,
            'cache_key' => $cacheKey,
            'cache_hit' => $hit,
            'ttl_seconds' => $ttl,
            'timestamp' => now()->toISOString()
        ];

        if ($hit) {
            Log::debug('Cache Hit', $context);
        } else {
            Log::debug('Cache Miss', $context);
        }
    }

    /**
     * Log performance metrics
     */
    public static function logPerformance(string $operation, float $executionTime, int $recordCount = null, int $memoryUsage = null): void
    {
        $context = [
            'operation' => $operation,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'record_count' => $recordCount,
            'memory_usage_mb' => $memoryUsage ? round($memoryUsage / 1024 / 1024, 2) : null,
            'timestamp' => now()->toISOString()
        ];

        if ($executionTime > 2.0) { // Slow query threshold
            Log::warning('Slow Query Detected', $context);
        } else {
            Log::debug('Performance Metrics', $context);
        }
    }

    /**
     * Log errors with categorization
     */
    public static function logError(string $operation, \Exception $exception, array $context = []): void
    {
        $errorContext = [
            'operation' => $operation,
            'error_type' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => self::sanitizeContext($context),
            'timestamp' => now()->toISOString()
        ];

        // Categorize errors
        $category = self::categorizeError($exception);
        $errorContext['error_category'] = $category;

        Log::error("Shopify Error [{$category}]: {$operation}", $errorContext);

        // Log stack trace in debug mode
        if (self::isDebugMode()) {
            Log::debug("Stack Trace: {$operation}", [
                'trace' => $exception->getTraceAsString()
            ]);
        }
    }

    /**
     * Sanitize variables to remove sensitive data
     */
    private static function sanitizeVariables(array $variables): array
    {
        $sanitized = [];
        foreach ($variables as $key => $value) {
            if (in_array(strtolower($key), ['password', 'token', 'secret', 'key', 'access_token'])) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize configuration to remove sensitive data
     */
    private static function sanitizeConfig(array $config): array
    {
        $sanitized = [];
        foreach ($config as $key => $value) {
            if (in_array(strtolower($key), ['api_secret', 'access_token', 'password', 'private_key'])) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize authentication details
     */
    private static function sanitizeAuthDetails(array $details): array
    {
        return self::sanitizeConfig($details);
    }

    /**
     * Sanitize context to remove sensitive data
     */
    private static function sanitizeContext(array $context): array
    {
        return self::sanitizeConfig($context);
    }

    /**
     * Categorize errors for better monitoring
     */
    private static function categorizeError(\Exception $exception): string
    {
        $message = strtolower($exception->getMessage());
        
        if (strpos($message, 'authentication') !== false || strpos($message, 'unauthorized') !== false) {
            return 'AUTHENTICATION';
        }
        
        if (strpos($message, 'rate limit') !== false || strpos($message, 'throttle') !== false) {
            return 'RATE_LIMIT';
        }
        
        if (strpos($message, 'network') !== false || strpos($message, 'connection') !== false || strpos($message, 'timeout') !== false) {
            return 'NETWORK';
        }
        
        if (strpos($message, 'graphql') !== false || strpos($message, 'query') !== false || strpos($message, 'field') !== false) {
            return 'GRAPHQL';
        }
        
        if (strpos($message, 'not found') !== false || $exception->getCode() === 404) {
            return 'NOT_FOUND';
        }
        
        if ($exception->getCode() >= 500) {
            return 'SERVER_ERROR';
        }
        
        return 'GENERAL';
    }
} 