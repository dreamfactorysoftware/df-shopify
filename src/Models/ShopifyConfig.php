<?php

namespace DreamFactory\Core\Shopify\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

class ShopifyConfig extends BaseServiceConfigModel
{
    protected $table = 'shopify_config';

    protected $fillable = [
        'service_id',
        'shop_domain',
        'api_key',
        'api_secret',
        'access_token',
        'api_version'
    ];

    protected $casts = [
        'service_id' => 'integer'
    ];

    protected $encrypted = [
        'api_secret',
        'access_token'
    ];

    protected $protected = [
        'api_secret', 
        'access_token'
    ];

    /**
     * Provides configuration field descriptions for the admin UI
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'shop_domain':
                $schema['label'] = 'Shop Domain';
                $schema['description'] = 'Your Shopify shop domain (e.g., mystore.myshopify.com)';
                break;
            case 'api_key':
                $schema['label'] = 'API Key';
                $schema['description'] = 'API Key from your Shopify Custom App';
                break;
            case 'api_secret':
                $schema['label'] = 'API Secret';
                $schema['description'] = 'API Secret from your Shopify Custom App';
                break;
            case 'access_token':
                $schema['label'] = 'Admin API Access Token';
                $schema['description'] = 'Admin API access token from your Shopify Custom App';
                break;
            case 'api_version':
                $schema['label'] = 'API Version';
                $schema['description'] = 'Shopify API version (e.g., 2023-10). Leave empty for latest.';
                break;
        }
    }
} 