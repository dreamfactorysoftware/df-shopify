# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-24

### Added
- **GraphQL API Support**: Migrated from direct cURL REST calls to official Shopify GraphQL API
- **Official Shopify SDK Integration**: Now uses `shopify/shopify-api` v5.10+ for robust API communication
- **Dynamic Field Selection**: GraphQL queries dynamically built based on requested fields for optimal performance
- **Cursor-based Pagination**: Modern GraphQL cursor pagination with pageInfo metadata
- **Enhanced Error Handling**: Comprehensive GraphQL error detection and reporting
- **Advanced Query Builder**: Flexible GraphQL query construction for all resource types
- **Response Transformer**: Seamless conversion of GraphQL nested responses to REST-like format

### Changed
- **API Version**: Updated default API version from `2023-10` to `2024-01`
- **Performance**: Significantly improved response times through optimized GraphQL queries
- **Data Structure**: Enhanced product variant data with additional GraphQL-only fields
- **Pagination**: Improved pagination with GraphQL cursor support and metadata
- **Error Messages**: More detailed error reporting with GraphQL-specific error context

### Enhanced
- **Products Resource**: Complete GraphQL implementation with dynamic field selection
- **Product Variants**: Enhanced variant data retrieval through GraphQL nested queries
- **Single Product Queries**: Optimized individual product fetching with GraphQL variables
- **Filtering**: Improved filter parsing and GraphQL query string generation
- **Logging**: Enhanced debug logging for GraphQL queries and responses

### Technical
- **SDK Dependencies**: Added official Shopify PHP SDK for future-proof API access
- **Context Management**: Proper Shopify Context initialization for SDK integration
- **Session Handling**: Robust session management for API authentication
- **GraphQL Infrastructure**: New QueryBuilder and ResponseTransformer classes
- **ID Conversion**: Automatic conversion between numeric and GraphQL global ID formats

### Future-Proofing
- **REST API Deprecation**: Prepared for eventual Shopify REST API deprecation
- **GraphQL-First**: Positioned for new Shopify features that may be GraphQL-only
- **SDK Benefits**: Access to advanced SDK features like automatic retries and rate limiting

## [1.0.0] - 2025-01-24

### Added
- **Complete Shopify Integration**: Full read-only access to Shopify Admin API
- **Products API**: List products, get individual products, and access product variants
  - Supports filtering by vendor, product type, status, and custom filters
  - Lightweight responses by default with optional large field inclusion
  - Performance optimized with configurable field selection
- **Orders API**: Comprehensive order data retrieval
  - Filter by financial status, fulfillment status, and date ranges
  - Complete order details including line items and addresses
  - Customer information embedded in order responses
- **Customers API**: Customer data management
  - List customers with filtering by state, email, and date ranges
  - Individual customer details with addresses and order history
  - Privacy-compliant customer data handling
- **Collections API**: Product collection management
  - List both smart and custom collections
  - Retrieve products within specific collections
  - Collection metadata and rule information
- **Advanced Filtering**: Server-side filtering using Shopify API parameters
  - SQL-like filter syntax support
  - Direct Shopify parameter passthrough
  - Efficient server-side processing to reduce bandwidth
- **Comprehensive API Documentation**: Full OpenAPI specification
  - All endpoints documented with parameters and responses
  - Interactive API testing through DreamFactory API docs
  - Detailed parameter descriptions and examples
- **Security Features**: Enterprise-grade security implementation
  - Read-only access to prevent accidental data modification
  - Encrypted storage of API credentials
  - Secure token handling and validation
- **Performance Optimizations**: Designed for production use
  - Lightweight default responses
  - Configurable field inclusion
  - Efficient API parameter handling
  - Proper error handling and logging

### Configuration
- **Shopify Private App Support**: Easy integration with Shopify Private Apps
- **Flexible Configuration**: Support for multiple API versions and shop domains
- **Credential Management**: Secure storage of API keys, secrets, and access tokens
- **Service Type**: Properly categorized as "Remote Service" in DreamFactory

### Technical Implementation
- **Laravel Integration**: Built on DreamFactory's Laravel foundation
- **REST Resource Pattern**: Follows DreamFactory's standard resource architecture
- **Database Migration**: Automated setup of configuration storage
- **Service Provider**: Proper Laravel service registration and discovery
- **Error Handling**: Comprehensive error catching and user-friendly messages
- **Logging**: Detailed logging for debugging and monitoring

### Documentation
- **Complete README**: Installation, configuration, and usage instructions
- **API Examples**: Comprehensive examples for all endpoints
- **Troubleshooting Guide**: Common issues and solutions
- **Security Guidelines**: Best practices for secure implementation 