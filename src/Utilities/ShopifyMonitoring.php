<?php

namespace DreamFactory\Core\Shopify\Utilities;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use DreamFactory\Core\Shopify\Models\ShopifyConfig;

class ShopifyMonitoring
{
    /**
     * Health check statuses
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Metrics collection window (in seconds)
     */
    const METRICS_WINDOW = 3600; // 1 hour

    /**
     * Perform comprehensive health check
     */
    public static function performHealthCheck(string $shopDomain, array $config = []): array
    {
        $healthCheck = [
            'shop_domain' => $shopDomain,
            'timestamp' => now()->toISOString(),
            'overall_status' => self::STATUS_HEALTHY,
            'checks' => []
        ];

        // Configuration validation
        $configCheck = self::validateConfiguration($config);
        $healthCheck['checks']['configuration'] = $configCheck;

        // Connectivity check
        $connectivityCheck = self::checkConnectivity($shopDomain, $config);
        $healthCheck['checks']['connectivity'] = $connectivityCheck;

        // Authentication check
        $authCheck = self::checkAuthentication($shopDomain, $config);
        $healthCheck['checks']['authentication'] = $authCheck;

        // Rate limiting check
        $rateLimitCheck = self::checkRateLimits($shopDomain);
        $healthCheck['checks']['rate_limits'] = $rateLimitCheck;

        // Circuit breaker status
        $circuitBreakerCheck = self::getCircuitBreakerStatus($shopDomain);
        $healthCheck['checks']['circuit_breakers'] = $circuitBreakerCheck;

        // Cache health
        $cacheCheck = self::checkCacheHealth();
        $healthCheck['checks']['cache'] = $cacheCheck;

        // Determine overall status
        $healthCheck['overall_status'] = self::determineOverallStatus($healthCheck['checks']);

        return $healthCheck;
    }

    /**
     * Validate service configuration
     */
    public static function validateConfiguration(array $config): array
    {
        $check = [
            'status' => self::STATUS_HEALTHY,
            'issues' => [],
            'recommendations' => []
        ];

        // Required fields
        $requiredFields = ['shop_domain', 'access_token', 'api_version'];
        foreach ($requiredFields as $field) {
            if (empty($config[$field])) {
                $check['issues'][] = "Missing required field: {$field}";
                $check['status'] = self::STATUS_CRITICAL;
            }
        }

        // Validate shop domain format
        if (!empty($config['shop_domain'])) {
            if (!preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $config['shop_domain']) &&
                !preg_match('/^[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/', $config['shop_domain'])) {
                $check['issues'][] = 'Invalid shop domain format';
                $check['status'] = self::STATUS_CRITICAL;
            }
        }

        // Validate API version
        if (!empty($config['api_version'])) {
            if (!preg_match('/^\d{4}-\d{2}$/', $config['api_version'])) {
                $check['issues'][] = 'Invalid API version format (expected: YYYY-MM)';
                $check['status'] = self::STATUS_WARNING;
            }
            
            // Check if API version is too old
            $apiDate = \DateTime::createFromFormat('Y-m', $config['api_version']);
            $cutoffDate = new \DateTime('-2 years');
            if ($apiDate && $apiDate < $cutoffDate) {
                $check['recommendations'][] = 'Consider upgrading to a newer API version';
                if ($check['status'] === self::STATUS_HEALTHY) {
                    $check['status'] = self::STATUS_WARNING;
                }
            }
        }

        // Validate access token format
        if (!empty($config['access_token'])) {
            if (strlen($config['access_token']) < 20) {
                $check['issues'][] = 'Access token appears to be invalid (too short)';
                $check['status'] = self::STATUS_CRITICAL;
            }
        }

        // Configuration recommendations
        if (empty($check['issues'])) {
            $check['recommendations'][] = 'Configuration appears valid';
        }

        return $check;
    }

    /**
     * Check connectivity to Shopify
     */
    public static function checkConnectivity(string $shopDomain, array $config): array
    {
        $check = [
            'status' => self::STATUS_HEALTHY,
            'response_time_ms' => null,
            'issues' => [],
            'last_checked' => now()->toISOString()
        ];

        try {
            $startTime = microtime(true);
            
            // Simple connectivity test using GraphQL introspection query
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://{$shopDomain}/admin/api/{$config['api_version']}/graphql.json",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['query' => '{ __schema { queryType { name } } }']),
                CURLOPT_HTTPHEADER => [
                    'X-Shopify-Access-Token: ' . ($config['access_token'] ?? ''),
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $responseTime = (microtime(true) - $startTime) * 1000;
            $check['response_time_ms'] = round($responseTime, 2);

            if ($curlError) {
                $check['issues'][] = "Connection error: {$curlError}";
                $check['status'] = self::STATUS_CRITICAL;
            } elseif ($httpCode >= 500) {
                $check['issues'][] = "Server error: HTTP {$httpCode}";
                $check['status'] = self::STATUS_CRITICAL;
            } elseif ($httpCode === 401) {
                $check['issues'][] = "Authentication failed: HTTP {$httpCode}";
                $check['status'] = self::STATUS_CRITICAL;
            } elseif ($httpCode !== 200) {
                $check['issues'][] = "Unexpected response: HTTP {$httpCode}";
                $check['status'] = self::STATUS_WARNING;
            }

            // Check response time
            if ($responseTime > 5000) {
                $check['issues'][] = "Slow response time: {$responseTime}ms";
                $check['status'] = self::STATUS_WARNING;
            }

        } catch (\Exception $e) {
            $check['issues'][] = "Connectivity check failed: " . $e->getMessage();
            $check['status'] = self::STATUS_CRITICAL;
        }

        return $check;
    }

    /**
     * Check authentication status
     */
    public static function checkAuthentication(string $shopDomain, array $config): array
    {
        $check = [
            'status' => self::STATUS_HEALTHY,
            'permissions' => [],
            'issues' => [],
            'last_checked' => now()->toISOString()
        ];

        try {
            // Test authentication with a simple query
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://{$shopDomain}/admin/api/{$config['api_version']}/graphql.json",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['query' => '{ shop { name plan { displayName } } }']),
                CURLOPT_HTTPHEADER => [
                    'X-Shopify-Access-Token: ' . ($config['access_token'] ?? ''),
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 401) {
                $check['issues'][] = 'Invalid access token or expired credentials';
                $check['status'] = self::STATUS_CRITICAL;
            } elseif ($httpCode === 403) {
                $check['issues'][] = 'Insufficient permissions for requested operations';
                $check['status'] = self::STATUS_CRITICAL;
            } elseif ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['errors'])) {
                    $check['issues'][] = 'Authentication query returned errors';
                    $check['status'] = self::STATUS_WARNING;
                } else {
                    $check['permissions'] = ['read_products', 'read_orders', 'read_customers', 'read_content'];
                }
            }

        } catch (\Exception $e) {
            $check['issues'][] = "Authentication check failed: " . $e->getMessage();
            $check['status'] = self::STATUS_CRITICAL;
        }

        return $check;
    }

    /**
     * Check rate limiting status
     */
    public static function checkRateLimits(string $shopDomain): array
    {
        $metricsKey = "shopify:metrics:{$shopDomain}:rate_limits";
        $metrics = Cache::get($metricsKey, []);

        $check = [
            'status' => self::STATUS_HEALTHY,
            'current_usage' => 0,
            'limit' => 40,
            'remaining' => 40,
            'reset_time' => null,
            'issues' => []
        ];

        if (!empty($metrics)) {
            $latestMetric = end($metrics);
            $check['current_usage'] = $latestMetric['calls_made'] ?? 0;
            $check['remaining'] = $latestMetric['calls_remaining'] ?? 40;
            $check['limit'] = $latestMetric['calls_made'] + $latestMetric['calls_remaining'];

            if ($check['remaining'] <= 5) {
                $check['issues'][] = 'Approaching rate limit threshold';
                $check['status'] = self::STATUS_WARNING;
            }

            if ($check['remaining'] <= 1) {
                $check['issues'][] = 'Rate limit nearly exhausted';
                $check['status'] = self::STATUS_CRITICAL;
            }
        }

        return $check;
    }

    /**
     * Get circuit breaker status
     */
    public static function getCircuitBreakerStatus(string $shopDomain): array
    {
        return ShopifyResilience::getHealthStatus($shopDomain);
    }

    /**
     * Check cache health
     */
    public static function checkCacheHealth(): array
    {
        $check = [
            'status' => self::STATUS_HEALTHY,
            'enabled' => ShopifyCache::isEnabled(),
            'hit_rate' => null,
            'issues' => []
        ];

        try {
            // Test cache functionality
            $testKey = 'shopify:health_check:' . uniqid();
            $testData = ['test' => true, 'timestamp' => time()];
            
            Cache::put($testKey, $testData, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved !== $testData) {
                $check['issues'][] = 'Cache read/write test failed';
                $check['status'] = self::STATUS_CRITICAL;
            }

        } catch (\Exception $e) {
            $check['issues'][] = "Cache health check failed: " . $e->getMessage();
            $check['status'] = self::STATUS_WARNING;
        }

        return $check;
    }

    /**
     * Collect and store metrics
     */
    public static function recordMetrics(string $shopDomain, array $metrics): void
    {
        $metricsKey = "shopify:metrics:{$shopDomain}";
        $currentMetrics = Cache::get($metricsKey, []);

        // Add timestamp to metrics
        $metrics['timestamp'] = time();

        // Add to metrics array
        $currentMetrics[] = $metrics;

        // Keep only recent metrics (last hour)
        $cutoff = time() - self::METRICS_WINDOW;
        $currentMetrics = array_filter($currentMetrics, function($metric) use ($cutoff) {
            return $metric['timestamp'] >= $cutoff;
        });

        // Store updated metrics
        Cache::put($metricsKey, array_values($currentMetrics), self::METRICS_WINDOW);
    }

    /**
     * Get performance metrics
     */
    public static function getPerformanceMetrics(string $shopDomain): array
    {
        $metricsKey = "shopify:metrics:{$shopDomain}";
        $metrics = Cache::get($metricsKey, []);

        $summary = [
            'total_requests' => count($metrics),
            'avg_response_time' => 0,
            'error_rate' => 0,
            'operations' => [],
            'time_window' => self::METRICS_WINDOW
        ];

        if (empty($metrics)) {
            return $summary;
        }

        $totalTime = 0;
        $errorCount = 0;
        $operationStats = [];

        foreach ($metrics as $metric) {
            // Calculate averages
            if (isset($metric['execution_time_ms'])) {
                $totalTime += $metric['execution_time_ms'];
            }

            if (isset($metric['has_error']) && $metric['has_error']) {
                $errorCount++;
            }

            // Track per-operation stats
            $operation = $metric['operation'] ?? 'unknown';
            if (!isset($operationStats[$operation])) {
                $operationStats[$operation] = [
                    'count' => 0,
                    'total_time' => 0,
                    'errors' => 0
                ];
            }

            $operationStats[$operation]['count']++;
            if (isset($metric['execution_time_ms'])) {
                $operationStats[$operation]['total_time'] += $metric['execution_time_ms'];
            }
            if (isset($metric['has_error']) && $metric['has_error']) {
                $operationStats[$operation]['errors']++;
            }
        }

        $summary['avg_response_time'] = $totalTime / count($metrics);
        $summary['error_rate'] = ($errorCount / count($metrics)) * 100;

        foreach ($operationStats as $operation => $stats) {
            $summary['operations'][$operation] = [
                'count' => $stats['count'],
                'avg_response_time' => $stats['total_time'] / $stats['count'],
                'error_rate' => ($stats['errors'] / $stats['count']) * 100
            ];
        }

        return $summary;
    }

    /**
     * Generate monitoring report
     */
    public static function generateReport(string $shopDomain, array $config = []): array
    {
        $report = [
            'shop_domain' => $shopDomain,
            'generated_at' => now()->toISOString(),
            'health_check' => self::performHealthCheck($shopDomain, $config),
            'performance_metrics' => self::getPerformanceMetrics($shopDomain),
            'cache_stats' => ShopifyCache::getStats($shopDomain),
            'recommendations' => []
        ];

        // Generate recommendations based on findings
        $report['recommendations'] = self::generateRecommendations($report);

        return $report;
    }

    /**
     * Generate recommendations based on monitoring data
     */
    private static function generateRecommendations(array $report): array
    {
        $recommendations = [];

        // Performance recommendations
        $avgResponseTime = $report['performance_metrics']['avg_response_time'] ?? 0;
        if ($avgResponseTime > 2000) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Average response time is high. Consider implementing caching or optimizing queries.'
            ];
        }

        // Error rate recommendations
        $errorRate = $report['performance_metrics']['error_rate'] ?? 0;
        if ($errorRate > 5) {
            $recommendations[] = [
                'type' => 'reliability',
                'priority' => 'high',
                'message' => 'Error rate is elevated. Review error logs and implement retry logic.'
            ];
        }

        // Cache recommendations
        if (!$report['cache_stats']['enabled']) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Enable caching to improve response times and reduce API calls.'
            ];
        }

        // Health check recommendations
        $overallStatus = $report['health_check']['overall_status'];
        if ($overallStatus !== self::STATUS_HEALTHY) {
            $recommendations[] = [
                'type' => 'health',
                'priority' => 'high',
                'message' => 'Health checks indicate issues. Review individual check results.'
            ];
        }

        return $recommendations;
    }

    /**
     * Determine overall status from individual checks
     */
    private static function determineOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::STATUS_CRITICAL, $statuses)) {
            return self::STATUS_CRITICAL;
        }

        if (in_array(self::STATUS_WARNING, $statuses)) {
            return self::STATUS_WARNING;
        }

        return self::STATUS_HEALTHY;
    }
} 