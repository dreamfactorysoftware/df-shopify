# DreamFactory Shopify Connector

## Requirements

- DreamFactory 7.1+
- PHP 8.3+
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
