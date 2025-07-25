<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled, additional logging and debugging information
    | will be collected. This should be disabled in production environments.
    |
    */
    'debug_mode' => env('SHOPIFY_DEBUG_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for Shopify API responses to improve
    | performance and reduce API call consumption.
    |
    */
    'caching' => [
        'enabled' => env('SHOPIFY_CACHE_ENABLED', true),
        'default_ttl' => env('SHOPIFY_CACHE_DEFAULT_TTL', 300), // 5 minutes
        'ttl' => [
            'products' => env('SHOPIFY_CACHE_PRODUCTS_TTL', 600),     // 10 minutes
            'orders' => env('SHOPIFY_CACHE_ORDERS_TTL', 120),         // 2 minutes  
            'customers' => env('SHOPIFY_CACHE_CUSTOMERS_TTL', 300),   // 5 minutes
            'collections' => env('SHOPIFY_CACHE_COLLECTIONS_TTL', 1800), // 30 minutes
            'single' => env('SHOPIFY_CACHE_SINGLE_TTL', 300),         // 5 minutes
            'variants' => env('SHOPIFY_CACHE_VARIANTS_TTL', 600),     // 10 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for failed API requests to improve resilience
    | against transient network issues and rate limiting.
    |
    */
    'retry' => [
        'enabled' => env('SHOPIFY_RETRY_ENABLED', true),
        'max_attempts' => env('SHOPIFY_RETRY_MAX_ATTEMPTS', 3),
        'base_delay' => env('SHOPIFY_RETRY_BASE_DELAY', 1000),      // milliseconds
        'max_delay' => env('SHOPIFY_RETRY_MAX_DELAY', 10000),       // milliseconds
        'exponential_base' => env('SHOPIFY_RETRY_EXPONENTIAL_BASE', 2),
        'jitter' => env('SHOPIFY_RETRY_JITTER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Circuit breaker settings to prevent cascading failures and provide
    | graceful degradation during service outages.
    |
    */
    'circuit_breaker' => [
        'enabled' => env('SHOPIFY_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('SHOPIFY_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'success_threshold' => env('SHOPIFY_CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 3),
        'timeout' => env('SHOPIFY_CIRCUIT_BREAKER_TIMEOUT', 60),     // seconds
        'window_time' => env('SHOPIFY_CIRCUIT_BREAKER_WINDOW', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and health check behavior for production
    | observability and alerting.
    |
    */
    'monitoring' => [
        'enabled' => env('SHOPIFY_MONITORING_ENABLED', true),
        'metrics_window' => env('SHOPIFY_MONITORING_METRICS_WINDOW', 3600), // 1 hour
        'health_check_interval' => env('SHOPIFY_MONITORING_HEALTH_CHECK_INTERVAL', 300), // 5 minutes
        'performance_thresholds' => [
            'slow_query_ms' => env('SHOPIFY_MONITORING_SLOW_QUERY_MS', 2000),
            'error_rate_threshold' => env('SHOPIFY_MONITORING_ERROR_RATE_THRESHOLD', 5), // percentage
            'response_time_warning_ms' => env('SHOPIFY_MONITORING_RESPONSE_TIME_WARNING_MS', 5000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior including what to log and sanitization
    | of sensitive information.
    |
    */
    'logging' => [
        'enabled' => env('SHOPIFY_LOGGING_ENABLED', true),
        'log_queries' => env('SHOPIFY_LOGGING_LOG_QUERIES', false), // Only in debug mode
        'log_responses' => env('SHOPIFY_LOGGING_LOG_RESPONSES', false), // Only in debug mode
        'log_performance' => env('SHOPIFY_LOGGING_LOG_PERFORMANCE', true),
        'log_rate_limits' => env('SHOPIFY_LOGGING_LOG_RATE_LIMITS', true),
        'log_authentication' => env('SHOPIFY_LOGGING_LOG_AUTHENTICATION', true),
        'sanitize_credentials' => env('SHOPIFY_LOGGING_SANITIZE_CREDENTIALS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Default API configuration and timeout settings.
    |
    */
    'api' => [
        'default_version' => env('SHOPIFY_API_DEFAULT_VERSION', '2024-01'),
        'timeout' => env('SHOPIFY_API_TIMEOUT', 30), // seconds
        'connect_timeout' => env('SHOPIFY_API_CONNECT_TIMEOUT', 5), // seconds
        'rate_limit_buffer' => env('SHOPIFY_API_RATE_LIMIT_BUFFER', 5), // calls remaining before warning
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings for the Shopify connector.
    |
    */
    'security' => [
        'validate_ssl' => env('SHOPIFY_SECURITY_VALIDATE_SSL', true),
        'allowed_domains' => env('SHOPIFY_SECURITY_ALLOWED_DOMAINS', '*.myshopify.com'),
        'token_validation' => env('SHOPIFY_SECURITY_TOKEN_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings.
    |
    */
    'performance' => [
        'enable_compression' => env('SHOPIFY_PERFORMANCE_COMPRESSION', true),
        'max_concurrent_requests' => env('SHOPIFY_PERFORMANCE_MAX_CONCURRENT', 10),
        'connection_pooling' => env('SHOPIFY_PERFORMANCE_CONNECTION_POOLING', true),
        'memory_limit_mb' => env('SHOPIFY_PERFORMANCE_MEMORY_LIMIT_MB', 128),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specifically for development and testing environments.
    |
    */
    'development' => [
        'mock_responses' => env('SHOPIFY_DEV_MOCK_RESPONSES', false),
        'log_all_requests' => env('SHOPIFY_DEV_LOG_ALL_REQUESTS', false),
        'disable_cache' => env('SHOPIFY_DEV_DISABLE_CACHE', false),
        'simulate_errors' => env('SHOPIFY_DEV_SIMULATE_ERRORS', false),
        'sandbox_mode' => env('SHOPIFY_DEV_SANDBOX_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features of the connector.
    |
    */
    'features' => [
        'graphql' => env('SHOPIFY_FEATURE_GRAPHQL', true),
        'webhooks' => env('SHOPIFY_FEATURE_WEBHOOKS', false),
        'bulk_operations' => env('SHOPIFY_FEATURE_BULK_OPERATIONS', false),
        'real_time_sync' => env('SHOPIFY_FEATURE_REAL_TIME_SYNC', false),
        'advanced_filtering' => env('SHOPIFY_FEATURE_ADVANCED_FILTERING', true),
    ],

]; 