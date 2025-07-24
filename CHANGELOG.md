# Changelog

All notable changes to the DreamFactory Shopify Connector will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-24

### Added
- Initial release of DreamFactory Shopify Connector
- Support for Products API endpoints:
  - List products with filtering and pagination
  - Get individual product details
  - Access product variants
- Support for Orders API endpoints:
  - List orders with status and date filtering
  - Get individual order details
  - Support for all Shopify order parameters
- Support for Customers API endpoints:
  - List customers with filtering
  - Get individual customer details
  - Email, phone, and state filtering
- Support for Collections API endpoints:
  - List all collections (smart and custom)
  - Get individual collection details
  - Browse products within collections
- Advanced filtering capabilities:
  - DreamFactory SQL-style filters
  - Direct Shopify API parameters
  - Server-side filtering for performance
- Performance optimizations:
  - Lightweight responses by default
  - Optional large field inclusion via `fields` parameter
  - Efficient pagination support
- Security features:
  - Read-only access (all write operations blocked)
  - Encrypted storage of API credentials
  - Input validation and sanitization
- Comprehensive API documentation:
  - OpenAPI/Swagger specifications
  - Parameter descriptions and examples
  - Response schema definitions
- Error handling:
  - Detailed error messages
  - Proper HTTP status codes
  - Shopify API error mapping
- Database migration for configuration storage
- ServiceProvider for DreamFactory integration
- Configuration model with encryption support

### Technical Details
- Compatible with DreamFactory 4.0+
- Requires PHP 8.0+
- Uses Shopify Admin API 2023-10
- Implements DreamFactory Remote Service pattern
- Direct cURL integration for optimal performance
- Comprehensive logging for troubleshooting 