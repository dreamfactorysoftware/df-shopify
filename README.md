# DreamFactory Shopify Connector

A modern DreamFactory service package for connecting to Shopify stores via the **official Shopify GraphQL API**. This connector provides high-performance, read-only access to your Shopify store data with future-proof GraphQL integration.

## 🚀 **Latest: GraphQL API Integration (v1.1.0)**

**Major upgrade with GraphQL support!** This version migrates from REST to GraphQL for:
- ⚡ **Faster performance** with optimized queries
- 🎯 **Precise data fetching** - get only the fields you need
- 🔮 **Future-proof** - prepared for Shopify's GraphQL-first direction
- 🛠️ **Official SDK** - powered by `shopify/shopify-api` for robust integration

## Features

* **🔥 GraphQL-Powered**: Built on Shopify's modern GraphQL API with official SDK
* **📦 Products**: List products, get individual products, and access product variants with dynamic field selection
* **📋 Orders**: List orders with advanced filtering by status, financial status, and date ranges  
* **👥 Customers**: List customers and access individual customer details with privacy controls
* **📚 Collections**: List collections and browse products within collections (smart & custom)
* **🎯 Dynamic Queries**: GraphQL queries built on-demand based on requested fields for optimal performance
* **🚀 Advanced Filtering**: Server-side filtering using GraphQL query syntax and Shopify parameters
* **📄 Cursor Pagination**: Modern GraphQL cursor-based pagination with metadata
* **🔒 Read-Only Access**: Secure read-only mode for data analytics and reporting
* **⚡ Performance Optimized**: Lightweight responses with optional large field inclusion

## Requirements

- DreamFactory 4.0+
- PHP 8.0+
- Shopify Admin API access (Private App)

## Installation

### 1. Add Package to Composer

Add the package to your DreamFactory's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/dreamfactory-development-packages/df-shopify"
        }
    ],
    "require": {
        "dreamfactory/df-shopify": "*"
    }
}
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Run Database Migration

```bash
php artisan migrate
```

### 4. Clear Application Cache

```bash
php artisan cache:clear
```

## Configuration

### 1. Create a Shopify Private App

1. Go to your Shopify Admin: `https://your-store.myshopify.com/admin`
2. Navigate to **Apps** > **App and sales channel settings**
3. Click **Develop apps** > **Create an app**
4. Configure Admin API access with these permissions:
   - `read_products`
   - `read_orders` 
   - `read_customers`
   - `read_collections`

### 2. Configure DreamFactory Service

1. Log into your DreamFactory Admin Console
2. Go to **Services** > **Create** > **Remote Service** > **Shopify Store**
3. Fill in the configuration:

| Field | Description | Example |
|-------|-------------|---------|
| **Name** | Service name | `my_shopify` |
| **Label** | Display name | `My Shopify Store` |
| **Shop Domain** | Your Shopify domain | `my-store.myshopify.com` |
| **API Key** | Admin API Key from Private App | `abc123...` |
| **API Secret** | Admin API Secret from Private App | `def456...` |
| **Access Token** | Admin API Access Token | `shpat_789...` |
| **API Version** | Shopify API version | `2023-10` |

### 3. Test the Connection

Use the API Docs to test your endpoints:
- `GET /api/v2/my_shopify/products`
- `GET /api/v2/my_shopify/orders`
- `GET /api/v2/my_shopify/customers`
- `GET /api/v2/my_shopify/collections`

## API Usage

### Products

```bash
# List products
GET /api/v2/my_shopify/products?limit=10

# Get specific product
GET /api/v2/my_shopify/products/123456789

# Get product variants
GET /api/v2/my_shopify/products/123456789/variants

# Filter products
GET /api/v2/my_shopify/products?filter=vendor='Nike' AND status='active'
```

### Orders

```bash
# List orders
GET /api/v2/my_shopify/orders?limit=50

# Filter by status
GET /api/v2/my_shopify/orders?financial_status=paid

# Filter by date range
GET /api/v2/my_shopify/orders?created_at_min=2023-01-01&created_at_max=2023-12-31
```

### Customers

```bash
# List customers
GET /api/v2/my_shopify/customers

# Get specific customer
GET /api/v2/my_shopify/customers/123456789

# Filter by state
GET /api/v2/my_shopify/customers?state=enabled
```

### Collections

```bash
# List collections
GET /api/v2/my_shopify/collections

# Get specific collection
GET /api/v2/my_shopify/collections/123456789

# Get products in collection
GET /api/v2/my_shopify/collections/123456789/products
```