# DreamFactory Shopify Connector

A DreamFactory service package for connecting to Shopify stores via the Admin API. This connector provides read-only access to your Shopify store data including products, orders, customers, and collections.

## Features

- **Products**: List products, get individual products, and access product variants
- **Orders**: List orders with filtering by status, financial status, and date ranges
- **Customers**: List customers and access individual customer details
- **Collections**: List collections and browse products within collections
- **Advanced Filtering**: Server-side filtering using Shopify API parameters
- **Performance Optimized**: Lightweight responses with optional large field inclusion
- **Read-Only Access**: Secure read-only mode for data analytics and reporting

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

## Advanced Features

### Field Selection

Use the `fields` parameter to limit returned data for better performance:

```bash
# Only return id, title, and price
GET /api/v2/my_shopify/products?fields=id,title,price

# Include large fields like description and images
GET /api/v2/my_shopify/products?fields=id,title,description,images
```

### Filtering

The connector supports both DreamFactory-style SQL filters and Shopify-specific parameters:

```bash
# DreamFactory SQL-style filter
GET /api/v2/my_shopify/products?filter=vendor='Nike' AND status='active'

# Direct Shopify parameters
GET /api/v2/my_shopify/orders?financial_status=paid&fulfillment_status=shipped
```

### Pagination

```bash
# Standard pagination
GET /api/v2/my_shopify/products?limit=25&offset=50

# Shopify cursor-based pagination
GET /api/v2/my_shopify/products?since_id=123456789
```

## Performance Optimization

### Lightweight Responses

By default, list endpoints exclude large fields for better performance:

- **Products**: Excludes `description`, `images`, `variants`, `options`
- **Orders**: Excludes `line_items`, `addresses`, `shipping_lines`
- **Customers**: Excludes `addresses`, `default_address`

Use the `fields` parameter to explicitly include these when needed.

### Server-Side Filtering

Filters are passed directly to Shopify's API for efficient server-side processing, reducing bandwidth and improving response times.

## Error Handling

The connector includes comprehensive error handling:

- **Authentication errors**: Clear messages for invalid credentials
- **Rate limiting**: Automatic handling of Shopify API rate limits
- **Not found errors**: Proper 404 responses for missing resources
- **Validation errors**: Detailed error messages for invalid requests

## Security

- **Read-only access**: All write operations (POST, PUT, DELETE) are blocked
- **Encrypted storage**: API secrets and access tokens are encrypted in the database
- **Input validation**: All parameters are validated before sending to Shopify

## Troubleshooting

### Common Issues

1. **"Invalid API key or access token"**
   - Verify your Private App credentials
   - Check that the API key and access token match

2. **"No operations defined in spec"**
   - Clear application cache: `php artisan cache:clear`
   - Restart your DreamFactory service

3. **Empty responses**
   - Check your Private App permissions
   - Verify the shop domain is correct (without `https://`)

### Logs

Check DreamFactory logs for detailed error information:

```bash
tail -f storage/logs/dreamfactory.log | grep -i shopify
```

## Support

For issues and questions:

1. Check the [DreamFactory Documentation](https://wiki.dreamfactory.com)
2. Review [Shopify Admin API Documentation](https://shopify.dev/docs/admin-api)
3. Contact DreamFactory Support

## License

This package is licensed under the same license as DreamFactory. 