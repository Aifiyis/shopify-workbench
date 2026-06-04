# User Management & Data Processing Modules - Implementation Guide

## Overview

This guide describes the two new modules added to the Shopify Workbench:

1. **User Management Module**: Hierarchical admin management system
2. **Data Processing Module**: Excel file processing with transformations

---

## 1. User Management Module

### Features

- **Three-Level Hierarchy**: Super Admin → Manager → Employee
- **Role-Based Access Control**: Each role can only manage subordinates
- **Company Information**: Store company names with admin accounts
- **Store Permissions**: Assign stores with view/edit access levels

### Database Schema

#### New Columns in `admins` table:
- `parent_admin_id` (nullable): Foreign key to parent admin
- `company_name` (nullable): Company/department name
- `is_manageable` (boolean): Default true

#### New Table (if needed for tracking):
- `admin_store_access`: Links admins to stores with access levels

### Access Points

- **URL**: `/admins`
- **Permissions**:
  - Super Admin: Can create/edit/delete managers and employees
  - Manager: Can create/edit/delete only employees
  - Employee: Cannot manage anyone

### UI Features

- Hierarchical tree view of all admins
- Create/Edit/Delete forms with validation
- Store permission assignment
- Active status management

### Example Usage (via tinker or tests)

```php
// Create super admin
$super = Admin::create([
    'name' => 'Super Admin',
    'email' => 'super@example.com',
    'password' => Hash::make('password'),
    'role' => 'super',
    'company_name' => 'Head Office',
    'is_active' => true,
]);

// Create manager under super admin
$manager = Admin::create([
    'name' => 'Manager 1',
    'email' => 'manager@example.com',
    'password' => Hash::make('password'),
    'role' => 'manager',
    'parent_admin_id' => $super->id,
    'company_name' => 'Branch A',
    'is_active' => true,
]);

// Create employee under manager
$employee = Admin::create([
    'name' => 'Employee 1',
    'email' => 'employee@example.com',
    'password' => Hash::make('password'),
    'role' => 'employee',
    'parent_admin_id' => $manager->id,
    'is_active' => true,
]);

// Check permissions
$manager->canManage($employee->id); // true
$super->canManage($manager->id); // true
$employee->canManage($manager->id); // false
```

### Model Methods

- `parent()`: Get parent admin
- `subordinates()`: Get direct subordinates
- `canManage($adminId)`: Check if can manage another admin
- `getSubordinateTree()`: Get hierarchical tree of subordinates

---

## 2. Data Processing Module

### Features

- **File Upload**: Upload Excel or CSV files with order data
- **Data Transformation**: Apply business logic transformations
- **Lookup Tables**: Match SKU to style, specs to color/size
- **Excel Export**: Generate formatted output files
- **File TTL**: Automatic deletion after 1 hour

### Database Schema

#### New Table: `processed_files`
- `id`: Primary key
- `admin_id`: Uploader admin ID
- `original_filename`: Original uploaded filename
- `processed_filename`: Generated output filename
- `file_path`: Full path to processed file
- `status`: completed, processing, or failed
- `uploaded_at`: Timestamp of upload
- `expires_at`: Deletion deadline (1 hour from upload)
- `is_downloaded`: Whether file was downloaded
- `downloaded_at`: When file was downloaded

### Access Points

- **URL**: `/data-processing`
- **Upload**: POST `/data-processing/upload`
- **Download**: GET `/data-processing/{id}/download`
- **Delete**: DELETE `/data-processing/{id}`

### File Requirements

#### Input File Format
Required columns:
- Order ID
- SKU
- Product Specs (multiline, colon-delimited attributes)
- Picture (image URL)
- Quantity

#### Output File Columns
1. **Filename**: Text after last `_` in source filename
2. **Order ID**: From source file
3. **Picture**: Product image URL
4. **Make Status**: Empty (for user to fill)
5. **Style**: Matched from SKU against lookup table
6. **Color**: Extracted from Product Specs
7. **Size**: Extracted from Product Specs
8. **Quantity**: Product quantity
9+. **Placeholders**: Empty columns for future use

### Lookup Tables

The system uses `storage/app/private/clothe-options-vlookup.xlsx` with two sheets:

#### Sheet 1: `style`
- Column A: Style identifier (matched against SKU)
- Column B: Style output value

#### Sheet 2: `color`
- Column A: Color identifier (matched against specs)
- Column B: Color output value

### Services

#### LookupService
```php
$lookupService = new LookupService();

// Get lookups (cached as JSON)
$styleLookup = $lookupService->getStyleLookup();
$colorLookup = $lookupService->getColorLookup();

// Match values
$style = $lookupService->matchStyle($sku, $styleLookup);
$color = $lookupService->matchColor($specs, $colorLookup);
$size = $lookupService->extractSize($specs);

// Reload cache if lookup file updated
$lookupService->reloadCache();
```

#### DataProcessingService
```php
$processingService = new DataProcessingService($lookupService);

$result = $processingService->processOrderFile($filePath);
// Returns:
// [
//   'success' => true,
//   'output_filename' => 'order_output_0601_09-0602_09.xlsx',
//   'output_path' => '/full/path/to/file.xlsx',
//   'rows_processed' => 42,
// ]
```

#### FileExpirationService
```php
$expirationService = new FileExpirationService(1); // 1 hour TTL

// Mark file for expiration (auto-deletes after 1 hour)
$expirationService->markForExpiration($filePath, $adminId);

// Manual cleanup (finds and deletes expired files)
$deletedCount = $expirationService->cleanExpiredFiles();

// Check if file is expired
$isExpired = $expirationService->isFileExpired($processedFileId);

// Get expiry info
$info = $expirationService->getExpiryInfo($processedFileId);
// Returns: ['expires_at' => Carbon, 'expires_in_minutes' => int, 'is_expired' => bool]
```

### UI Features

- Drag-and-drop file upload
- File list with upload time and expiry countdown
- Download links (disabled after expiration)
- Delete functionality
- Success/error messages

### Automatic Cleanup

To automatically clean expired files, register the command in your task scheduler:

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('processed-files:clean')->hourly();
}
```

Or run manually:
```bash
php artisan processed-files:clean --hours=1
```

---

## File Locations

### Controllers
- `app/Http/Controllers/AdminManagementController.php`
- `app/Http/Controllers/DataProcessingController.php`

### Services
- `app/Services/LookupService.php`
- `app/Services/DataProcessingService.php`
- `app/Services/FileExpirationService.php`

### Models
- `app/Models/Admin.php` (updated)
- `app/Models/ProcessedFile.php`

### Views
- `resources/views/admins/index.blade.php`
- `resources/views/admins/form.blade.php`
- `resources/views/admins/_tree.blade.php`
- `resources/views/data-processing/index.blade.php`
- `resources/views/layouts/app.blade.php`

### Migrations
- `database/migrations/2026_06_03_000001_extend_admins_table.php`
- `database/migrations/2026_06_03_000002_create_processed_files_table.php`
- `database/migrations/2026_06_03_000003_update_admin_role_enum.php`

### Tests
- `tests/Unit/AdminHierarchyTest.php`
- `tests/Unit/DataProcessingTest.php`

---

## Configuration

### File Storage Directories
```
storage/app/private/clothe-options-vlookup.xlsx (lookup table)
storage/app/public/processed_files/ (output files)
storage/app/private/lookups/ (cached JSON lookups)
storage/app/temp/ (temporary uploads)
```

### Environment Settings
No additional .env variables required. All settings use defaults.

### TTL Settings
Default: 1 hour (3600 seconds)

To change, modify `FileExpirationService` constructor call or use:
```php
new FileExpirationService(24); // 24 hours
```

---

## Routes

### Admin Management Routes
```
GET  /admins                 (admin.index)
GET  /admins/create         (admin.create)
POST /admins                (admin.store)
GET  /admins/{id}/edit      (admin.edit)
PUT  /admins/{id}           (admin.update)
DELETE /admins/{id}         (admin.destroy)
```

### Data Processing Routes
```
GET  /data-processing               (data-processing.index)
POST /data-processing/upload        (data-processing.upload)
GET  /data-processing/{id}/download (data-processing.download)
DELETE /data-processing/{id}        (data-processing.delete)
```

---

## Testing

Run all tests:
```bash
php artisan test
```

Run specific tests:
```bash
php artisan test tests/Unit/AdminHierarchyTest.php
php artisan test tests/Unit/DataProcessingTest.php
```

---

## Future Enhancements

1. **User Management**:
   - Add custom roles/permissions
   - Implement audit logging
   - Add email notifications on account creation

2. **Data Processing**:
   - Support for additional file formats (JSON, XML)
   - Batch processing multiple files
   - Custom transformation rule builder
   - Data validation reports
   - Schedule processing tasks
   - Real-time processing progress tracking

3. **General**:
   - API endpoints for programmatic access
   - Export/import admin structures
   - Admin activity logs
   - Rate limiting on file uploads

---

## Troubleshooting

### Files Not Uploading
- Check `storage/app/public/processed_files/` directory exists and is writable
- Verify file size is under max upload limit
- Check file format is Excel or CSV

### Lookup Matching Not Working
- Verify `clothe-options-vlookup.xlsx` exists in `storage/app/private/`
- Check lookup file has `style` and `color` sheets
- Try clearing lookup cache: `LookupService::reloadCache()`

### Permission Denied Errors
- Check admin role (must be super or manager to manage others)
- Verify parent-child relationships in database
- Check `parent_admin_id` is set correctly

### Files Not Deleting After 1 Hour
- Run cleanup command manually: `php artisan processed-files:clean`
- Check `expires_at` timestamp in database
- Verify `storage/app/public/processed_files/` permissions

---

## Support

For issues or questions, please refer to:
- CLAUDE.md for architecture documentation
- Test files for usage examples
- Individual service classes for detailed method documentation

