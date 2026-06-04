# Quick Start Guide - New Modules

## Installation Complete ✓

The following modules have been successfully implemented:

### 1. User Management Module
**Status**: Ready to use
**Location**: `/admins`

To get started:
1. Login as super admin
2. Navigate to "Admin Management" in sidebar
3. Create managers and employees in hierarchical structure
4. Assign stores and permissions

### 2. Data Processing Module  
**Status**: Ready to use
**Location**: `/data-processing`

To get started:
1. Login as any admin
2. Navigate to "Data Processing" in sidebar
3. Upload an Excel/CSV file with order data
4. System automatically processes and generates output
5. Download processed file (expires in 1 hour)

---

## Quick Setup Steps

### Step 1: Create Test Admin Hierarchy

```bash
php artisan tinker
```

Then in tinker:
```php
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

// Create super admin
$super = Admin::create([
    'name' => 'Super Admin',
    'email' => 'super@test.local',
    'password' => Hash::make('password123'),
    'role' => 'super',
    'company_name' => 'Head Office',
    'is_active' => true,
]);

// Create manager
$manager = Admin::create([
    'name' => 'Manager One',
    'email' => 'manager@test.local',
    'password' => Hash::make('password123'),
    'role' => 'manager',
    'parent_admin_id' => $super->id,
    'company_name' => 'Branch A',
    'is_active' => true,
]);

exit
```

### Step 2: Login and Test

1. Go to `http://localhost:8001/login`
2. Login with super admin account
3. Navigate to Admin Management or Data Processing

### Step 3: Test Lookups (Optional)

Create `storage/app/private/clothe-options-vlookup.xlsx` with:
- Sheet 1: `style` (Column A = style key, Column B = style output)
- Sheet 2: `color` (Column A = color name, Column B = color output)

---

## Files Created/Modified

### New Files
```
app/Http/Controllers/AdminManagementController.php
app/Http/Controllers/DataProcessingController.php
app/Services/LookupService.php
app/Services/DataProcessingService.php
app/Services/FileExpirationService.php
app/Models/ProcessedFile.php
app/Console/Commands/CleanExpiredProcessedFiles.php
resources/views/admins/index.blade.php
resources/views/admins/form.blade.php
resources/views/admins/_tree.blade.php
resources/views/data-processing/index.blade.php
resources/views/layouts/app.blade.php
database/migrations/2026_06_03_000001_extend_admins_table.php
database/migrations/2026_06_03_000002_create_processed_files_table.php
database/migrations/2026_06_03_000003_update_admin_role_enum.php
tests/Unit/AdminHierarchyTest.php
tests/Unit/DataProcessingTest.php
```

### Modified Files
```
app/Models/Admin.php (added relationships and methods)
routes/web.php (added new routes)
```

---

## Database Schema

### Admins Table Changes
- Added `parent_admin_id` (nullable bigint, self-referential)
- Added `company_name` (nullable string)
- Added `is_manageable` (boolean, default true)
- Updated `role` enum to include 'employee'

### New Table: ProcessedFiles
```sql
CREATE TABLE processed_files (
  id BIGINT PRIMARY KEY,
  admin_id BIGINT (FK to admins),
  original_filename VARCHAR,
  processed_filename VARCHAR UNIQUE,
  file_path VARCHAR,
  status VARCHAR (completed/processing/failed),
  uploaded_at TIMESTAMP,
  expires_at TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

---

## Key Features

### User Management
- ✓ Three-level hierarchy (Super → Manager → Employee)
- ✓ Company information tracking
- ✓ Role-based permission controls
- ✓ Store access assignment
- ✓ Hierarchical UI tree view

### Data Processing
- ✓ Excel/CSV file upload
- ✓ Data transformation
- ✓ Lookup table matching
- ✓ Excel output generation
- ✓ 1-hour TTL with auto-deletion
- ✓ Drag-and-drop UI

---

## Running Tests

```bash
php artisan test
# All 10 tests passing ✓
```

---

## Support Documentation

- Full guide: `IMPLEMENTATION_GUIDE.md`
- Architecture: `CLAUDE.md`

---

## Success Criteria Met ✓

- ✅ User hierarchy system working
- ✅ Permission controls enforced
- ✅ File upload/processing working
- ✅ 1-hour TTL implemented
- ✅ All tests passing

Enjoy! 🎉

