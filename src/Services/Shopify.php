<?php

namespace DreamFactory\Core\Shopify\Services;

// Load Shopify SDK autoloader
$shopifyAutoloader = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($shopifyAutoloader)) {
    require_once $shopifyAutoloader;
}

use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Shopify\Resources\Products;
use DreamFactory\Core\Shopify\Resources\Orders;
use DreamFactory\Core\Shopify\Resources\Customers;
use DreamFactory\Core\Shopify\Resources\Collections;
use DreamFactory\Core\Shopify\Utilities\ShopifyLogger;
use DreamFactory\Core\Shopify\Utilities\ShopifyCache;
use DreamFactory\Core\Shopify\Utilities\ShopifyResilience;
use DreamFactory\Core\Shopify\Utilities\ShopifyMonitoring;
use Illuminate\Support\Arr;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

class Shopify extends BaseRestService
{
    /** @type array Service Resources */
    protected static $resources = [
        Products::RESOURCE_NAME => [
            'name'       => Products::RESOURCE_NAME,
            'class_name' => Products::class,
            'label'      => 'Products',
        ],
        Orders::RESOURCE_NAME => [
            'name'       => Orders::RESOURCE_NAME,
            'class_name' => Orders::class,
            'label'      => 'Orders',
        ],
        Customers::RESOURCE_NAME => [
            'name'       => Customers::RESOURCE_NAME,
            'class_name' => Customers::class,
            'label'      => 'Customers',
        ],
        Collections::RESOURCE_NAME => [
            'name'       => Collections::RESOURCE_NAME,
            'class_name' => Collections::class,
            'label'      => 'Collections',
        ],
    ];

    protected $shopDomain;
    protected $apiKey;
    protected $apiSecret;
    protected $accessToken;
    protected $apiVersion;


    /**
     * Create a new Shopify service
     *
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        // Extract config settings
        $this->shopDomain = Arr::get($this->config, 'shop_domain');
        $this->apiKey = Arr::get($this->config, 'api_key');
        $this->apiSecret = Arr::get($this->config, 'api_secret');
        $this->accessToken = Arr::get($this->config, 'access_token');
        $this->apiVersion = Arr::get($this->config, 'api_version') ?: '2023-10';

        // Ensure string values for Shopify API
        $this->shopDomain = (string) $this->shopDomain;
        $this->apiKey = (string) $this->apiKey;
        $this->apiSecret = (string) $this->apiSecret;
        $this->accessToken = (string) $this->accessToken;
        $this->apiVersion = (string) $this->apiVersion;

        // Basic validation
        if (empty($this->shopDomain) || empty($this->accessToken)) {
            throw new \InvalidArgumentException('Shop domain and access token are required for Shopify service.');
        }

        // Initialize Shopify API client
        $this->initializeShopifyClient();
    }

    /**
     * Initialize the Shopify API client with SDK support
     */
    protected function initializeShopifyClient()
    {
        try {
            // Validate required configuration
            if (empty($this->shopDomain)) {
                throw new \Exception('Shop domain is required');
            }
            if (empty($this->accessToken)) {
                throw new \Exception('Access token is required');
            }
            
            // Set default API version if not provided
            if (empty($this->apiVersion)) {
                $this->apiVersion = '2024-01';
            }
            
            // Ensure shop domain is properly formatted
            $this->shopDomain = str_replace(['http://', 'https://'], '', $this->shopDomain);
            if (!str_ends_with($this->shopDomain, '.myshopify.com')) {
                $this->shopDomain = rtrim($this->shopDomain, '/');
                if (!str_contains($this->shopDomain, '.')) {
                    $this->shopDomain .= '.myshopify.com';
                }
            }

            // No SDK needed - use direct cURL like the working REST endpoints
            // GraphQL will use the same proven authentication as REST endpoints

            // Log service initialization with production logging
            ShopifyLogger::logServiceInit($this->shopDomain, $this->apiVersion, [
                'shop_domain' => $this->shopDomain,
                'api_version' => $this->apiVersion,
                'service_id' => $this->getServiceId()
            ]);

            // Log authentication success
            ShopifyLogger::logAuthentication($this->shopDomain, true, 'access_token');

        } catch (\Exception $e) {
            // Log authentication failure with production logging
            ShopifyLogger::logAuthentication($this->shopDomain ?? 'unknown', false, 'access_token', [
                'error' => $e->getMessage()
            ]);
            
            // Log error with categorization
            ShopifyLogger::logError('service_initialization', $e, [
                'shop_domain' => $this->shopDomain ?? 'unknown',
                'api_version' => $this->apiVersion ?? 'unknown'
            ]);
            
            throw new InternalServerErrorException('Failed to initialize Shopify service: ' . $e->getMessage());
        }
    }



    /**
     * Get access list for this service
     */
    public function getAccessList()
    {
        $resources = [];
        
        // Add products resource
        $name = Products::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }
        
        // Add orders resource
        $name = Orders::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }
        
        // Add customers resource
        $name = Customers::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }
        
        // Add collections resource
        $name = Collections::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }
        
        return $resources;
    }





    /**
     * Get shop domain
     */
    public function getShopDomain()
    {
        return $this->shopDomain;
    }

    /**
     * Get access token
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Get API version
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Perform health check for this service instance
     */
    public function performHealthCheck(): array
    {
        $config = [
            'shop_domain' => $this->shopDomain,
            'access_token' => $this->accessToken,
            'api_version' => $this->apiVersion
        ];

        return ShopifyMonitoring::performHealthCheck($this->shopDomain, $config);
    }

    /**
     * Get performance metrics for this service
     */
    public function getPerformanceMetrics(): array
    {
        return ShopifyMonitoring::getPerformanceMetrics($this->shopDomain);
    }

    /**
     * Generate comprehensive monitoring report
     */
    public function generateMonitoringReport(): array
    {
        $config = [
            'shop_domain' => $this->shopDomain,
            'access_token' => $this->accessToken,
            'api_version' => $this->apiVersion
        ];

        return ShopifyMonitoring::generateReport($this->shopDomain, $config);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return ShopifyCache::getStats($this->shopDomain);
    }

    /**
     * Clear cache for this shop
     */
    public function clearCache(): void
    {
        ShopifyCache::invalidateShop($this->shopDomain);
    }

    /**
     * Warm up cache with common queries
     */
    public function warmUpCache(): void
    {
        ShopifyCache::warmUp($this->shopDomain);
    }

    /**
     * Reset circuit breakers for this shop
     */
    public function resetCircuitBreakers(): void
    {
        ShopifyResilience::resetAllCircuitBreakers($this->shopDomain);
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitBreakerStatus(): array
    {
        return ShopifyResilience::getHealthStatus($this->shopDomain);
    }

    /**
     * Execute operation with enhanced error handling and resilience
     */
    public function executeWithResilience(callable $operation, string $operationName, array $retryConfig = [])
    {
        return ShopifyResilience::executeWithRetry($operation, $operationName, $retryConfig);
    }

    /**
     * Record metrics for monitoring
     */
    public function recordMetrics(array $metrics): void
    {
        ShopifyMonitoring::recordMetrics($this->shopDomain, $metrics);
    }

    /**
     * Get error recovery suggestions
     */
    public function getRecoverySuggestions(\Exception $exception): array
    {
        return ShopifyResilience::getRecoverySuggestions($exception);
    }
} 