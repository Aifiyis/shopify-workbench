# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Shopify Internal Management Workbench** - A Laravel 8 application for managing multiple Shopify stores, syncing orders, transforming data fields (Ruby-to-PHP translation), and exporting orders to Excel format.

### Core Stack

- **Framework**: Laravel 8
- **Database**: MySQL 5.7+
- **Key Libraries**: Shopify API (guzzlehttp), Maatwebsite/Excel (PHPExcel)
- **Authentication**: Laravel Session Guard for Admin users
- **Frontend**: Blade templates with vanilla JS

## Common Development Tasks

### Running the Application

```bash
# Start development server
php artisan serve

# Run migrations
php artisan migrate

# Access the app at http://localhost:8000/login
```

### Running Tests

```bash
# Run all tests (when implemented)
php artisan test

# Run specific test
php artisan test tests/Unit/Services/Transformers/ExtraTransformerTest.php
```

### Database Operations

```bash
# Create new migration
php artisan make:migration create_table_name

# Rollback migrations
php artisan migrate:rollback

# Fresh database
php artisan migrate:fresh
```

### Debugging

```bash
# Interactive shell (tinker)
php artisan tinker

# Check logs
tail -f storage/logs/laravel.log
```

## Architecture & Key Files

### Authentication Layer
- **File**: `app/Http/Controllers/Auth/AdminLoginController.php`
- **Guard**: `admin` (uses `admins` table, not `users`)
- **Config**: `config/auth.php` - defines admin provider and guard

### Data Transformation (Core Logic)
The project translates 6 Ruby methods into PHP Transformer classes:

1. **NameTransformer** (`app/Services/Transformers/NameTransformer.php`)
   - Ruby: Concatenates all line_item titles
   - Use: Extract product names from order

2. **ValTransformer** (`app/Services/Transformers/ValTransformer.php`)
   - Ruby: Checks if title contains "3,99", returns "H"
   - Use: Mark special price products

3. **UrlTransformer** (`app/Services/Transformers/UrlTransformer.php`)
   - Ruby: Extracts picture URL from line_item.properties where name="picture"
   - Use: Get product image URL

4. **SubpicTransformer** (`app/Services/Transformers/SubpicTransformer.php`)
   - Ruby: Extracts filename from URL (everything after last "/")
   - Use: Get filename from URL

5. **ExtraTransformer** (`app/Services/Transformers/ExtraTransformer.php`) - **Most Complex**
   - Ruby: 40+ if-elsif conditions generating filename based on color/size/type
   - Use: Generate standardized filenames for manufacturing
   - Methods: `normalizeTag()`, `normalizeColor()`, `normalizeSize()`, `normalizePjSize()`, `generateFilename()`

6. **GetnotesTransformer** (`app/Services/Transformers/GetnotesTransformer.php`)
   - Ruby: Extracts notes from line_item.properties where name="notes"
   - Use: Get custom user notes

**Entry Point**: `OrderFieldTransformer` (`app/Services/OrderFieldTransformer.php`) - orchestrates all transformers

### Service Layer

- **ShopifyService** (`app/Services/ShopifyService.php`)
  - Method: `fetchOrders($store, $startDate, $endDate)` - calls Shopify REST API
  - Method: `callApi()` - generic API caller with auth token
  - Uses: Guzzle HTTP client

- **OrderCacheService** (`app/Services/OrderCacheService.php`)
  - Method: `cacheOrders($orders, $store)` - saves to DB with TTL
  - Method: `getCachedOrders($store, $filters)` - retrieves from cache
  - Method: `isCacheValid($store)` - checks if cache expired
  - Cache TTL: 1 hour (configurable via `setCacheTtl()`)

- **ExcelExportService** (`app/Services/ExcelExportService.php`)
  - Method: `export($orders, $startDate, $endDate)` - generates XLSX
  - File location: `storage/exports/{filename}.xlsx`
  - Columns: Matches `de_order_with_image_0203230600.xlsx` structure
  - Library: PHPExcel (via Maatwebsite/Excel v1.1.5)

### Controllers

- **AdminLoginController** - Authentication (login/logout)
- **DashboardController** - Store selection screen
- **OrderController** - Order list, refresh, export (main business logic)
- **ExportController** - File download handler

### Models

All models use relationship methods:
- `Admin::stores()` - many-to-many via `admin_store_access`
- `ShopifyStore::orders()` - one-to-many
- `Order::lineItems()` - one-to-many
- Key method: `Admin::canAccessStore($storeId)` - permission check

### Database Schema

**admins** - id, name, email, password, role (super/manager), is_active, last_login

**shopify_stores** - id, shop_name, shop_url, access_token, is_active, last_synced_at

**admin_store_access** - id, admin_id (FK), store_id (FK), access_level (view/edit)

**orders** - id, store_id (FK), shopify_order_id, order_date, order_name, customer_name, total_price, currency, status, line_items_count, cached_at, expires_at

**order_line_items** - id, order_id (FK), shopify_line_item_id, product_title, product_type, quantity, option1, option3, product_tags, sku, multi_types, picture_url, pic_name, extra_details, custom_text, raw_properties (JSON)

## Important Design Decisions

### Why Excel v1 (PHPExcel)?
- Laravel 8 compatibility constraint (older PHP 7.4)
- Newer versions require PHP 8.1+
- If upgrading to PHP 8.1+, migrate to `maatwebsite/excel ^3.1`

### Why separate Transformer classes?
- Each Ruby rule = 1 testable unit
- Easy to modify individual rules without touching others
- Clear responsibility separation

### Why local database cache?
- Reduce Shopify API rate limit hits
- Faster order listing
- Offline access to recent data
- Historical tracking

### Permission Model
- **Super admin**: All stores automatically
- **Manager**: Only assigned stores
- Check: `Auth::guard('admin')->user()->canAccessStore($storeId)`

## Code Style Conventions

- **Comments**: Only on complex logic (ExtraTransformer rules, data mappings)
- **Naming**: Descriptive class/method names (e.g., `normalizeColor()` not `nc()`)
- **Error Handling**: Try-catch in Transformers returns "ERROR: {message}" (allows export to continue)
- **Logging**: Use `\Log::error()` for API failures
- **Validation**: Request class validation via `Request::validate()`

## Common Modifications

### Adding a new Transformer Rule

1. Create `app/Services/Transformers/RuleTransformer.php`
2. Implement `transform()` method with try-catch
3. Register in `OrderFieldTransformer::__construct()`
4. Add to `transformLineItem()` method

### Extending Excel Export Columns

1. Edit `ExcelExportService::$columns` array
2. Update `writeOrderLine()` to add new data
3. Adjust `adjustColumnWidths()` if needed

### Modifying Cache TTL

In `OrderCacheService::__construct()`:
```php
$this->cacheTtlHours = 4; // Change from 1 to 4 hours
// Or via method: $service->setCacheTtl(4);
```

### Adding New Admin Permission

```php
// Add in database/seeders or via tinker
AdminStoreAccess::create([
    'admin_id' => $adminId,
    'store_id' => $storeId,
    'access_level' => 'edit', // or 'view'
]);
```

## API Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation completed",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description"
}
```

## Troubleshooting

**Q: Orders not showing up after refresh?**
- Check Shopify access_token is valid
- Check logs: `storage/logs/laravel.log`
- Verify store has orders in selected date range

**Q: Excel export fails?**
- Check `storage/exports/` exists and is writable
- Check PHPExcel version compatibility (v1 for PHP 7.4)

**Q: Permission denied on orders?**
- Check `admin_store_access` record exists
- Verify admin role (super admin bypasses check)

**Q: Transformers returning "ERROR:"?**
- Each transformer is wrapped in try-catch and returns error message
- Check Shopify API response format (properties, line_items structure)

## Next Steps / TODOs

- [ ] Implement Shopify OAuth flow (currently manual token input)
- [ ] Add store management UI
- [ ] Implement scheduled synchronization jobs
- [ ] Add search and advanced filtering
- [ ] Implement batch operations
- [ ] Add audit logging
- [ ] Performance: Add pagination for large order lists
- [ ] Consider migrating to Laravel 11 + PHP 8.2+ for newer packages

## Dependencies Versions

- `laravel/framework: ^8.83`
- `shopify/shopify-api: ^1.0` (PHP 7.4 compatible version)
- `maatwebsite/excel: ^1.1` (PHPExcel wrapper, not the newer v3)
- `guzzlehttp/guzzle: ^7.10`

Do NOT upgrade to Excel v3 without upgrading PHP to 8.1+.

