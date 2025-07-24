<?php

namespace DreamFactory\Core\Shopify;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Shopify\Models\ShopifyConfig;
use DreamFactory\Core\Shopify\Services\Shopify;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our service type to DreamFactory
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'           => 'shopify',
                    'label'          => 'Shopify Store',
                    'description'    => 'Connect to Shopify stores for products, orders, and customer data',
                    'group'          => ServiceTypeGroups::REMOTE,
                    'config_handler' => ShopifyConfig::class,
                    'factory'        => function ($config) {
                        return new Shopify($config);
                    },
                ])
            );
        });
    }

    public function boot()
    {
        // Load database migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
} 