<?php

namespace DreamFactory\Core\Shopify\Utilities;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ShopifyResilience
{
    /**
     * Circuit breaker states
     */
    const STATE_CLOSED = 'closed';
    const STATE_OPEN = 'open';
    const STATE_HALF_OPEN = 'half_open';

    /**
     * Default retry configuration
     */
    const DEFAULT_RETRY_CONFIG = [
        'max_attempts' => 3,
        'base_delay' => 1000,      // milliseconds
        'max_delay' => 10000,      // milliseconds
        'exponential_base' => 2,
        'jitter' => true
    ];

    /**
     * Circuit breaker configuration
     */
    const CIRCUIT_BREAKER_CONFIG = [
        'failure_threshold' => 5,
        'success_threshold' => 3,
        'timeout' => 60,           // seconds
        'window_time' => 300       // seconds (5 minutes)
    ];

    /**
     * Execute operation with retry logic
     */
    public static function executeWithRetry(callable $operation, string $operationName, array $retryConfig = []): mixed
    {
        $config = array_merge(self::DEFAULT_RETRY_CONFIG, $retryConfig);
        $maxAttempts = $config['max_attempts'];
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                // Check circuit breaker before attempting
                if (!self::isCircuitBreakerOpen($operationName)) {
                    $startTime = microtime(true);
                    $result = $operation();
                    $executionTime = microtime(true) - $startTime;

                    // Record success
                    self::recordSuccess($operationName);
                    
                    ShopifyLogger::logPerformance($operationName, $executionTime);
                    
                    return $result;
                }
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Record failure
                self::recordFailure($operationName, $e);
                
                // Check if we should retry
                if (!self::shouldRetry($e, $attempt, $maxAttempts)) {
                    break;
                }

                // Calculate delay before retry
                $delay = self::calculateDelay($attempt, $config);
                
                ShopifyLogger::logError($operationName, $e, [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'retry_delay_ms' => $delay,
                    'will_retry' => $attempt < $maxAttempts
                ]);

                if ($attempt < $maxAttempts) {
                    usleep($delay * 1000); // Convert to microseconds
                }
            }
        }

        // All retries exhausted
        throw new \Exception(
            "Operation '{$operationName}' failed after {$maxAttempts} attempts. Last error: " . 
            ($lastException ? $lastException->getMessage() : 'Unknown error'),
            $lastException ? $lastException->getCode() : 500,
            $lastException
        );
    }

    /**
     * Check if circuit breaker is open for an operation
     */
    public static function isCircuitBreakerOpen(string $operationName): bool
    {
        $state = self::getCircuitBreakerState($operationName);
        
        switch ($state) {
            case self::STATE_OPEN:
                // Check if timeout period has passed
                if (self::shouldTransitionToHalfOpen($operationName)) {
                    self::setCircuitBreakerState($operationName, self::STATE_HALF_OPEN);
                    return false;
                }
                return true;
                
            case self::STATE_HALF_OPEN:
                return false;
                
            case self::STATE_CLOSED:
            default:
                return false;
        }
    }

    /**
     * Record successful operation
     */
    private static function recordSuccess(string $operationName): void
    {
        $key = "shopify:circuit_breaker:{$operationName}";
        $data = Cache::get($key, [
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure_time' => null
        ]);

        $data['success_count']++;
        
        // If in half-open state, check if we can close the circuit
        if ($data['state'] === self::STATE_HALF_OPEN && 
            $data['success_count'] >= self::CIRCUIT_BREAKER_CONFIG['success_threshold']) {
            $data['state'] = self::STATE_CLOSED;
            $data['failure_count'] = 0;
            $data['success_count'] = 0;
        }

        Cache::put($key, $data, self::CIRCUIT_BREAKER_CONFIG['window_time']);
    }

    /**
     * Record failed operation
     */
    private static function recordFailure(string $operationName, \Exception $exception): void
    {
        $key = "shopify:circuit_breaker:{$operationName}";
        $data = Cache::get($key, [
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure_time' => null
        ]);

        $data['failure_count']++;
        $data['success_count'] = 0; // Reset success count on failure
        $data['last_failure_time'] = time();

        // Check if we should open the circuit
        if ($data['failure_count'] >= self::CIRCUIT_BREAKER_CONFIG['failure_threshold']) {
            $data['state'] = self::STATE_OPEN;
        }

        Cache::put($key, $data, self::CIRCUIT_BREAKER_CONFIG['window_time']);
    }

    /**
     * Get current circuit breaker state
     */
    private static function getCircuitBreakerState(string $operationName): string
    {
        $key = "shopify:circuit_breaker:{$operationName}";
        $data = Cache::get($key, ['state' => self::STATE_CLOSED]);
        return $data['state'];
    }

    /**
     * Set circuit breaker state
     */
    private static function setCircuitBreakerState(string $operationName, string $state): void
    {
        $key = "shopify:circuit_breaker:{$operationName}";
        $data = Cache::get($key, [
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure_time' => null
        ]);
        
        $data['state'] = $state;
        if ($state === self::STATE_HALF_OPEN) {
            $data['success_count'] = 0;
        }
        
        Cache::put($key, $data, self::CIRCUIT_BREAKER_CONFIG['window_time']);
    }

    /**
     * Check if circuit breaker should transition from open to half-open
     */
    private static function shouldTransitionToHalfOpen(string $operationName): bool
    {
        $key = "shopify:circuit_breaker:{$operationName}";
        $data = Cache::get($key);
        
        if (!$data || !isset($data['last_failure_time'])) {
            return false;
        }
        
        $timeSinceLastFailure = time() - $data['last_failure_time'];
        return $timeSinceLastFailure >= self::CIRCUIT_BREAKER_CONFIG['timeout'];
    }

    /**
     * Determine if operation should be retried
     */
    private static function shouldRetry(\Exception $exception, int $attempt, int $maxAttempts): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        // Don't retry on authentication errors
        if (strpos(strtolower($exception->getMessage()), 'authentication') !== false ||
            strpos(strtolower($exception->getMessage()), 'unauthorized') !== false) {
            return false;
        }

        // Don't retry on 4xx errors (except rate limiting)
        $code = $exception->getCode();
        if ($code >= 400 && $code < 500 && $code !== 429) {
            return false;
        }

        // Retry on network errors, timeouts, 5xx errors, and rate limiting
        return true;
    }

    /**
     * Calculate delay for retry attempt
     */
    private static function calculateDelay(int $attempt, array $config): int
    {
        $delay = $config['base_delay'] * pow($config['exponential_base'], $attempt - 1);
        $delay = min($delay, $config['max_delay']);

        // Add jitter to prevent thundering herd
        if ($config['jitter']) {
            $jitter = mt_rand(0, (int)($delay * 0.1));
            $delay += $jitter;
        }

        return $delay;
    }

    /**
     * Get health status for monitoring
     */
    public static function getHealthStatus(string $shopDomain): array
    {
        $operations = ['products', 'orders', 'customers', 'collections'];
        $status = [
            'shop_domain' => $shopDomain,
            'overall_status' => 'healthy',
            'circuit_breakers' => [],
            'timestamp' => now()->toISOString()
        ];

        foreach ($operations as $operation) {
            $key = "shopify:circuit_breaker:{$operation}";
            $data = Cache::get($key, [
                'state' => self::STATE_CLOSED,
                'failure_count' => 0,
                'success_count' => 0
            ]);

            $status['circuit_breakers'][$operation] = [
                'state' => $data['state'],
                'failure_count' => $data['failure_count'],
                'success_count' => $data['success_count'],
                'healthy' => $data['state'] !== self::STATE_OPEN
            ];

            if ($data['state'] === self::STATE_OPEN) {
                $status['overall_status'] = 'degraded';
            }
        }

        return $status;
    }

    /**
     * Reset circuit breaker for operation
     */
    public static function resetCircuitBreaker(string $operationName): void
    {
        $key = "shopify:circuit_breaker:{$operationName}";
        Cache::forget($key);
    }

    /**
     * Reset all circuit breakers for a shop
     */
    public static function resetAllCircuitBreakers(string $shopDomain): void
    {
        $operations = ['products', 'orders', 'customers', 'collections'];
        foreach ($operations as $operation) {
            self::resetCircuitBreaker($operation);
        }
    }

    /**
     * Get error recovery suggestions
     */
    public static function getRecoverySuggestions(\Exception $exception): array
    {
        $message = strtolower($exception->getMessage());
        $code = $exception->getCode();
        
        $suggestions = [];

        if (strpos($message, 'authentication') !== false || $code === 401) {
            $suggestions[] = 'Check API credentials and access token validity';
            $suggestions[] = 'Verify shop domain is correct';
            $suggestions[] = 'Ensure API permissions are properly configured';
        } elseif (strpos($message, 'rate limit') !== false || $code === 429) {
            $suggestions[] = 'Implement request throttling';
            $suggestions[] = 'Use cache to reduce API calls';
            $suggestions[] = 'Consider upgrading Shopify plan for higher limits';
        } elseif (strpos($message, 'network') !== false || strpos($message, 'timeout') !== false) {
            $suggestions[] = 'Check network connectivity';
            $suggestions[] = 'Increase timeout values';
            $suggestions[] = 'Verify Shopify service status';
        } elseif (strpos($message, 'graphql') !== false || strpos($message, 'field') !== false) {
            $suggestions[] = 'Verify GraphQL query syntax';
            $suggestions[] = 'Check if requested fields exist in current API version';
            $suggestions[] = 'Review Shopify GraphQL schema documentation';
        } elseif ($code >= 500) {
            $suggestions[] = 'Check Shopify service status';
            $suggestions[] = 'Retry the operation after a delay';
            $suggestions[] = 'Contact Shopify support if issue persists';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Review error logs for more details';
            $suggestions[] = 'Check Shopify API documentation';
        }

        return $suggestions;
    }
} 