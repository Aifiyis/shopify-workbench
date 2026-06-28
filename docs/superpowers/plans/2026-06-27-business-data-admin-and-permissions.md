# Business Data Admin and Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Chinese-language CRUD backend for SKU product types, order-processing configuration, craft hierarchy, employees, positions, permissions, and administrator accounts without losing the existing imported data.

**Architecture:** Add `product_types` as the shared product-name catalog, then link the existing SKU and order-processing tables to it while retaining legacy text snapshots. Add employees, multi-position assignments, role/position permissions, soft deletes, Policies, server-side CRUD pages, and small JSON endpoints for inline product-type and craft creation. Existing business data remains global; `company_name` is descriptive only.

**Tech Stack:** Laravel 8, PHP 7.3-compatible syntax, Blade, Eloquent, SQLite/MySQL-compatible migrations, Tailwind CDN, Tom Select 2.4.3, vanilla JavaScript, PHPUnit 9, Playwright.

**Execution Override (2026-06-28):** For all remaining tasks, the user waived mandatory pre-implementation RED runs to reduce token and subagent usage. Focused tests must still be written and pass before every commit. Do not run the full suite or use the local database for tests.

---

## File Structure

- `database/migrations/2026_06_27_000004_*` through `000007_*`: product-type links, employee/position/permission schema, soft deletes, business staff links.
- `app/Models/ProductType.php`, `Employee.php`, `Position.php`, `Permission.php`: focused domain models and relationships.
- `app/Services/BusinessDataBackfillService.php`: idempotent product-type, employee, settlement, and permission backfill.
- `app/Services/PermissionService.php`: effective role/position permissions and delegation checks.
- `app/Policies/*Policy.php`: business-resource and account authorization.
- `app/Http/Controllers/*Controller.php`: one controller per business resource.
- `app/Http/Requests/*Request.php`: request validation and employee-position eligibility rules.
- `resources/views/business/*`, `employees/*`, `positions/*`: compact Chinese CRUD pages.
- `resources/views/components/*`, `resources/js/admin-ui.js`: shared table, confirmation-dialog, modal, and searchable-select behavior.
- `resources/lang/zh_CN/*`: validation, pagination, authentication, and password messages.

## Task 0: Preserve and Commit the Existing SKU Import Foundation

**Files:**
- Existing: `app/Services/SkuCleaningService.php`
- Existing: `app/Services/SkuMatchProductTypeImportService.php`
- Existing: `app/Models/{ProcessingCraftNode,ProductProcessingCraft,SkuMatchProductType}.php`
- Existing: `database/migrations/2026_06_27_000001_*.php` through `000003_*.php`
- Existing: `database/seeders/SkuMatchProductTypeSeeder.php`
- Existing tests: `tests/Unit/SkuCleaningServiceTest.php`, `tests/Unit/SkuMatchProductTypeSeederTest.php`, `tests/Feature/SkuMatchProductTypeImportServiceTest.php`

- [ ] **Step 1: Verify the prerequisite tests against SQLite memory**

Run each command separately:

```powershell
php artisan test tests\Unit\SkuCleaningServiceTest.php
php artisan test tests\Unit\SkuMatchProductTypeSeederTest.php
php artisan test tests\Feature\SkuMatchProductTypeImportServiceTest.php
```

Expected: 3, 1, and 4 tests pass. Do not run the full suite.

- [ ] **Step 2: Confirm generated/private paths are not staged**

```powershell
git status --short
git diff --cached --name-only
```

Expected: `outputs/`, `scripts/__pycache__/`, database backups, logs, and private source files are absent from the staged list.

- [ ] **Step 3: Commit only the SKU import foundation**

```powershell
git add app/Services/SkuCleaningService.php app/Services/SkuMatchProductTypeImportService.php
git add app/Models/ProcessingCraftNode.php app/Models/ProductProcessingCraft.php app/Models/SkuMatchProductType.php
git add database/migrations/2026_06_27_000001_create_processing_craft_nodes_table.php
git add database/migrations/2026_06_27_000002_create_product_processing_craft_table.php
git add database/migrations/2026_06_27_000003_create_sku_match_product_type_table.php
git add database/seeders/SkuMatchProductTypeSeeder.php
git add tests/Unit/SkuCleaningServiceTest.php tests/Unit/SkuMatchProductTypeSeederTest.php
git add tests/Feature/SkuMatchProductTypeImportServiceTest.php
git commit -m "feat: import SKU product type processing data"
```

Expected: one commit containing only the prerequisite implementation.

## Task 1: Add Product Types and Soft-Delete Business Schema

**Files:**
- Create: `database/migrations/2026_06_27_000004_create_product_types_and_link_business_tables.php`
- Create: `database/migrations/2026_06_27_000005_add_soft_deletes_to_business_tables.php`
- Create: `app/Models/ProductType.php`
- Modify: `app/Models/SkuMatchProductType.php`
- Modify: `app/Models/ProductProcessingCraft.php`
- Modify: `app/Models/ProcessingCraftNode.php`
- Test: `tests/Feature/BusinessAdminSchemaTest.php`

- [ ] **Step 1: Write the failing schema and relationship test**

```php
<?php

namespace Tests\Feature;

use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessAdminSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_types_link_both_business_tables()
    {
        $type = ProductType::create(['chinese_name' => '彩图刺绣']);
        $sku = SkuMatchProductType::create([
            'original_sku' => 'RAW-1',
            'cleaned_sku' => 'CLEAN-1',
            'chinese_name' => '彩图刺绣',
            'product_type_id' => $type->id,
        ]);
        $processing = ProductProcessingCraft::create([
            'chinese_name' => '彩图刺绣',
            'product_type_id' => $type->id,
        ]);

        $this->assertTrue(Schema::hasColumn('sku_match_product_type', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('product_processing_craft', 'deleted_at'));
        $this->assertSame($type->id, $sku->productType->id);
        $this->assertSame($type->id, $processing->productType->id);
    }
}
```

- [ ] **Step 2: Run the test and verify RED**

```powershell
php artisan test tests\Feature\BusinessAdminSchemaTest.php
```

Expected: FAIL because `product_types` and `product_type_id` do not exist.

- [ ] **Step 3: Create additive SQLite-safe migrations**

Migration `000004` must create:

```php
Schema::create('product_types', function (Blueprint $table) {
    $table->id();
    $table->string('chinese_name')->unique()->comment('中文名称');
    $table->softDeletes();
    $table->timestamps();
});

Schema::table('sku_match_product_type', function (Blueprint $table) {
    $table->unsignedBigInteger('product_type_id')->nullable()->index()->after('cleaned_sku');
});

Schema::table('product_processing_craft', function (Blueprint $table) {
    $table->unsignedBigInteger('product_type_id')->nullable()->unique()->after('chinese_name');
});
```

Migration `000005` must add `deleted_at` to `sku_match_product_type`, `product_processing_craft`, and `processing_craft_nodes`. Do not drop or rebuild existing tables. Added relationship columns remain nullable for the backfill phase; models and requests enforce them after backfill.

- [ ] **Step 4: Add models and relationships**

`ProductType` must use `SoftDeletes`, expose `skuMatches()` and `processingCraft()`, and define Chinese `FIELD_LABELS`. Existing business models must use `SoftDeletes` and add:

```php
public function productType()
{
    return $this->belongsTo(ProductType::class);
}
```

Add `product_type_id` to both `$fillable` arrays.

- [ ] **Step 5: Run the schema test and existing import tests**

```powershell
php artisan test tests\Feature\BusinessAdminSchemaTest.php
php artisan test tests\Feature\SkuMatchProductTypeImportServiceTest.php
```

Expected: PASS. The existing import service remains unchanged in this task because `product_type_id` is nullable; Task 3 performs the required backfill.

- [ ] **Step 6: Commit**

```powershell
git add database/migrations/2026_06_27_000004_create_product_types_and_link_business_tables.php
git add database/migrations/2026_06_27_000005_add_soft_deletes_to_business_tables.php
git add app/Models/ProductType.php app/Models/SkuMatchProductType.php
git add app/Models/ProductProcessingCraft.php app/Models/ProcessingCraftNode.php
git add tests/Feature/BusinessAdminSchemaTest.php
git commit -m "feat: add product type catalog and soft deletes"
```

## Task 2: Add Employees, Positions, and Permission Schema

**Files:**
- Create: `database/migrations/2026_06_27_000006_create_employee_position_permission_tables.php`
- Create: `database/migrations/2026_06_27_000007_link_business_staff_and_soft_delete_admins.php`
- Create: `app/Models/Employee.php`
- Create: `app/Models/Position.php`
- Create: `app/Models/Permission.php`
- Modify: `app/Models/Admin.php`
- Modify: `app/Models/SkuMatchProductType.php`
- Modify: `app/Models/ProductProcessingCraft.php`
- Test: `tests/Feature/EmployeePermissionSchemaTest.php`

- [ ] **Step 1: Write the failing employee relationship test**

```php
public function test_employee_can_have_multiple_positions_and_optional_admin()
{
    $admin = Admin::create([
        'name' => 'Test Employee',
        'email' => 'employee@example.test',
        'password' => \Illuminate\Support\Facades\Hash::make('test-password'),
        'role' => 'employee',
        'is_active' => true,
    ]);
    $employee = Employee::create([
        'name' => '李鑫',
        'company_name' => '千兴科技',
        'admin_id' => $admin->id,
        'is_active' => true,
    ]);
    $employee->positions()->attach([
        Position::create(['name' => '广告', 'code' => 'advertising'])->id,
        Position::create(['name' => '图画处理', 'code' => 'artwork_processing'])->id,
    ]);

    $this->assertCount(2, $employee->positions);
    $this->assertSame($employee->id, $admin->employee->id);
}
```

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Feature\EmployeePermissionSchemaTest.php
```

Expected: FAIL because employee and permission tables do not exist.

- [ ] **Step 3: Create employee and permission tables**

Migration `000006` must create:

```php
employees: id, name, company_name nullable, supervisor_id nullable,
           admin_id nullable unique, is_active boolean default true,
           deleted_at, timestamps
positions: id, name, code unique, is_active boolean default true,
           deleted_at, timestamps
permissions: id, name, code unique, is_delegable boolean default false,
             timestamps
employee_position: employee_id, position_id, timestamps,
                   unique(employee_id, position_id)
position_permission: position_id, permission_id, timestamps,
                     unique(position_id, permission_id)
role_permission: role string, permission_id, timestamps,
                 unique(role, permission_id)
```

Create foreign keys inside these new tables because SQLite can enforce them at creation time. `supervisor_id` uses `set null`; employee/admin deletion uses `set null`; pivot rows cascade.

Migration `000007` must add nullable indexed staff IDs and `settlement_method`:

```php
sku_match_product_type.product_lister_employee_id
product_processing_craft.order_processor_employee_id
product_processing_craft.artwork_processor_employee_id
product_processing_craft.procurement_processor_employee_id
product_processing_craft.settlement_method
admins.deleted_at
```

Do not add post-creation SQLite foreign constraints to existing tables.

- [ ] **Step 4: Add model relationships and soft deletes**

`Employee` belongs to supervisor/admin and belongs to many positions. `Position` belongs to many employees and permissions. `Permission` belongs to many positions. `Admin` uses `SoftDeletes` and has one employee. Business models expose the four staff relationships.

- [ ] **Step 5: Run tests**

```powershell
php artisan test tests\Feature\EmployeePermissionSchemaTest.php
php artisan test tests\Feature\BusinessAdminSchemaTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add database/migrations/2026_06_27_000006_create_employee_position_permission_tables.php
git add database/migrations/2026_06_27_000007_link_business_staff_and_soft_delete_admins.php
git add app/Models/Employee.php app/Models/Position.php app/Models/Permission.php
git add app/Models/Admin.php app/Models/SkuMatchProductType.php app/Models/ProductProcessingCraft.php
git add tests/Feature/EmployeePermissionSchemaTest.php
git commit -m "feat: add employee position permission model"
```

## Task 3: Backfill Product Types, Employees, Settlement, and Default Permissions

**Files:**
- Create: `app/Services/BusinessDataBackfillService.php`
- Create: `database/seeders/BusinessAdminSetupSeeder.php`
- Create: `app/Console/Commands/BootstrapQxAdmin.php`
- Test: `tests/Feature/BusinessDataBackfillServiceTest.php`
- Test: `tests/Feature/BootstrapQxAdminTest.php`

- [ ] **Step 1: Write failing backfill tests**

Cover these exact cases:

```php
public function test_backfill_uses_union_of_business_names_without_fake_skus()
{
    SkuMatchProductType::create([
        'original_sku' => 'SKU-A', 'cleaned_sku' => 'SKU-A',
        'chinese_name' => '有SKU类型', 'product_lister' => '张三',
    ]);
    ProductProcessingCraft::create([
        'chinese_name' => '仅订单处理类型',
        'procurement_processor' => '李梦瑶（月结）',
    ]);

    app(BusinessDataBackfillService::class)->run();

    $this->assertDatabaseCount('product_types', 2);
    $this->assertDatabaseCount('sku_match_product_type', 1);
    $this->assertDatabaseHas('product_processing_craft', [
        'settlement_method' => '月结',
        'procurement_processor' => '李梦瑶（月结）',
    ]);
}
```

Also assert: product listers receive advertising; order/artwork/procurement names receive their corresponding positions; `李鑫/万芸君` and `预览图` stay unlinked.

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Feature\BusinessDataBackfillServiceTest.php
```

Expected: FAIL because the service is missing.

- [ ] **Step 3: Implement idempotent backfill**

`BusinessDataBackfillService::run(): array` must:

1. Seed positions `advertising`, `operations`, `procurement`, `order_processing`, `artwork_processing`.
2. Seed permissions from the design and role-permission defaults for `manager`.
3. Seed default position-permission mappings.
4. Insert product types from the union of both legacy `chinese_name` columns.
5. Update both business tables with `product_type_id`.
6. Normalize single employee names with `trim`; reject blank, values containing `/`, and `预览图`.
7. Assign product listers to advertising by default.
8. Parse `姓名（结算方式）` with `/^(.+?)（([^）]+)）$/u` and write settlement separately.
9. Update staff ID columns while preserving legacy text.
10. Return counts for product types, employees, links, unresolved values, and permissions.

Use `firstOrCreate`, `syncWithoutDetaching`, and chunked updates. Never delete source rows.

- [ ] **Step 4: Add the standalone setup Seeder**

```php
class BusinessAdminSetupSeeder extends Seeder
{
    public function run(BusinessDataBackfillService $service)
    {
        $result = $service->run();
        Log::info('Business admin setup completed.', $result);
        return $result;
    }
}
```

Do not register it in `DatabaseSeeder`.

- [ ] **Step 5: Write the failing qxadmin command test**

Assert that running the command with a test-only environment password creates an active manager named `qxadmin`, email `test@qq.com`, parent `Admin`, a linked employee record with company name `千兴科技`, and a hash verified by `Hash::check`.

- [ ] **Step 6: Implement `admin:bootstrap-qxadmin`**

The command accepts no password option. It reads `QXADMIN_INITIAL_PASSWORD`; when absent it calls `$this->secret('请输入 qxadmin 初始密码')`. It uses `updateOrCreate` for the account and employee. Never print or log the password.

- [ ] **Step 7: Run focused tests**

```powershell
php artisan test tests\Feature\BusinessDataBackfillServiceTest.php
php artisan test tests\Feature\BootstrapQxAdminTest.php
```

Expected: PASS and a second backfill run keeps all counts stable.

- [ ] **Step 8: Commit**

```powershell
git add app/Services/BusinessDataBackfillService.php
git add database/seeders/BusinessAdminSetupSeeder.php
git add app/Console/Commands/BootstrapQxAdmin.php
git add tests/Feature/BusinessDataBackfillServiceTest.php tests/Feature/BootstrapQxAdminTest.php
git commit -m "feat: backfill product types staff and permissions"
```

## Task 4: Implement Effective Permissions and Policies

**Files:**
- Create: `app/Services/PermissionService.php`
- Create: `app/Policies/{SkuMatchProductType,ProductType,ProductProcessingCraft,ProcessingCraftNode,Employee,Position,Admin}Policy.php`
- Modify: `app/Models/Admin.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Test: `tests/Unit/PermissionServiceTest.php`
- Test: `tests/Feature/BusinessAuthorizationTest.php`

- [ ] **Step 1: Write failing permission tests**

Test all rules:

- every active logged-in account can view business lists;
- advertising/operations employees can manage SKU/product types;
- procurement/order/artwork employees can manage order processing and crafts;
- `admin_accounts.manage` permits only employee-role account management;
- manager role can manage employee accounts, employees, positions, and delegable permissions;
- only super can manage manager/super accounts;
- no user can delegate a permission they do not possess or one with `is_delegable = false`.

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Unit\PermissionServiceTest.php
php artisan test tests\Feature\BusinessAuthorizationTest.php
```

Expected: FAIL because permission resolution and Policies are missing.

- [ ] **Step 3: Implement `PermissionService`**

Public API:

```php
public function has(Admin $admin, string $permissionCode): bool;
public function delegableFor(Admin $admin): Collection;
public function canManageAccount(Admin $actor, Admin $target = null, string $targetRole = 'employee'): bool;
```

`has()` returns true for super, then checks `role_permission`, then active non-deleted employee positions and their permissions. `delegableFor()` intersects possessed permissions with `is_delegable = true`.

- [ ] **Step 4: Register policies and super override**

Add model-policy mappings in `AuthServiceProvider`. `Gate::before` returns true only for active non-deleted super admins. Controllers must still use Policies rather than checking role strings directly.

- [ ] **Step 5: Run tests**

```powershell
php artisan test tests\Unit\PermissionServiceTest.php
php artisan test tests\Feature\BusinessAuthorizationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/PermissionService.php app/Policies app/Models/Admin.php app/Providers/AuthServiceProvider.php
git add tests/Unit/PermissionServiceTest.php tests/Feature/BusinessAuthorizationTest.php
git commit -m "feat: authorize business actions by role and position"
```

## Task 5: Build the Shared Chinese Admin UI Foundation

**Files:**
- Modify: `config/app.php`
- Create: `resources/lang/zh_CN/{auth,pagination,passwords,validation}.php`
- Modify: `resources/views/layouts/app.blade.php`
- Create: `resources/views/components/{flash,confirm-delete,form-errors}.blade.php`
- Create: `resources/js/admin-ui.js`
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/ChineseAdminLayoutTest.php`

- [ ] **Step 1: Write a failing Chinese layout test**

Authenticate an admin, request `/dashboard`, and assert the response contains `千兴工作台`, `工作台`, `数据处理`, `SKU 产品类型`, `订单处理配置`, `工艺层级管理`, and `退出登录`, with `<html lang="zh-CN">`.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\ChineseAdminLayoutTest.php
```

Record the current result. A pre-implementation failure is not required; the same command must pass before commit.

- [ ] **Step 3: Add Chinese locale files and set the locale**

Set `'locale' => 'zh_CN'` and `'fallback_locale' => 'zh_CN'`. Validation attributes must include all new fields, for example:

```php
'attributes' => [
    'original_sku' => '原始 SKU',
    'cleaned_sku' => '清洗后 SKU',
    'product_type_id' => '产品类型',
    'craft_id' => '工艺',
    'product_lister_employee_id' => '上品人',
]
```

- [ ] **Step 4: Rebuild the shared layout**

Use one restrained sidebar, active-route styling, compact typography, mobile horizontal navigation fallback, flash messages, and a reusable `<dialog>` confirmation component. Include pinned Tom Select assets:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js" defer></script>
```

Do not expose edit links when Policies deny the action.

- [ ] **Step 5: Add reusable JavaScript behavior**

`admin-ui.js` initializes `[data-searchable-select]`, opens and submits the delete dialog, and exposes `window.AdminUI.addSelectOption(select, option, choose)` for AJAX-created product types/crafts. It must render craft options using `data-depth` indentation and preserve full-path search text.

- [ ] **Step 6: Run the test and compile assets**

```powershell
php artisan test tests\Feature\ChineseAdminLayoutTest.php
npm run dev
```

Expected: test PASS and Mix exits 0.

- [ ] **Step 7: Commit**

```powershell
git add config/app.php resources/lang/zh_CN resources/views/layouts/app.blade.php
git add resources/views/components resources/js/app.js resources/js/admin-ui.js resources/css/app.css
git add tests/Feature/ChineseAdminLayoutTest.php public/js/app.js public/css/app.css mix-manifest.json
git commit -m "feat: add shared Chinese admin interface"
```

## Task 6: Build SKU Mapping and Product Type CRUD

**Files:**
- Create: `app/Http/Controllers/SkuMatchProductTypeController.php`
- Create: `app/Http/Controllers/ProductTypeController.php`
- Create: `app/Http/Requests/{StoreSkuMatchProductType,UpdateSkuMatchProductType,StoreProductType,UpdateProductType}Request.php`
- Modify: `routes/web.php`
- Create: `resources/views/business/sku-product-types/{index,form,_sku-table,_type-table}.blade.php`
- Test: `tests/Feature/SkuProductTypeCrudTest.php`

- [ ] **Step 1: Write failing CRUD tests**

Cover:

- authenticated users can search by original SKU, cleaned SKU, product type, and lister;
- results paginate 50 rows;
- authorized users create/edit/soft-delete SKU mappings;
- unauthorized employees receive 403 on mutations;
- product-type JSON creation returns `{id, chinese_name}`;
- duplicate names return 422 with a Chinese edit-route message;
- product types with SKU or processing references cannot be deleted.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\SkuProductTypeCrudTest.php
```

Record the current result. A pre-implementation failure is not required; the same command must pass before commit.

- [ ] **Step 3: Add resource routes and JSON endpoint**

Inside `auth:admin`:

```php
Route::resource('sku-product-types', SkuMatchProductTypeController::class)->except('show');
Route::resource('product-types', ProductTypeController::class)->except('show');
Route::post('product-types/quick-create', [ProductTypeController::class, 'quickStore'])
    ->name('product-types.quick-store');
```

- [ ] **Step 4: Implement controllers and requests**

`index` uses eager loading and server-side filters, then `paginate(50)->withQueryString()`. Store/update requests require unique original SKU among non-deleted rows, valid product type, and an active employee holding advertising or operations. Save both ID and legacy text snapshot.

Product-type update runs in a transaction and synchronizes legacy `chinese_name` snapshots in both business tables.

- [ ] **Step 5: Build the two-tab views**

Tabs are `SKU 映射` and `产品类型`. Forms use searchable product type and lister selects. Product type supports Tom Select `create` callback posting to `product-types.quick-store` and selecting the returned ID.

- [ ] **Step 6: Run tests**

```powershell
php artisan test tests\Feature\SkuProductTypeCrudTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add app/Http/Controllers/SkuMatchProductTypeController.php app/Http/Controllers/ProductTypeController.php
git add app/Http/Requests routes/web.php resources/views/business/sku-product-types
git add tests/Feature/SkuProductTypeCrudTest.php
git commit -m "feat: add SKU and product type management"
```

## Task 7: Build Order Processing Configuration CRUD

**Files:**
- Create: `app/Http/Controllers/OrderProcessingController.php`
- Create: `app/Http/Requests/{StoreOrderProcessing,UpdateOrderProcessing}Request.php`
- Create: `resources/views/business/order-processing/{index,form}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrderProcessingCrudTest.php`

- [ ] **Step 1: Add focused tests**

Cover search/pagination, single product-type configuration, position-filtered multi-employee assignments, settlement values, authorized CRUD, soft delete, and preservation of all legacy processor text and single-ID fields.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\OrderProcessingCrudTest.php
```

- [ ] **Step 3: Add routes, request validation, and controller**

```php
Route::resource('order-processing', OrderProcessingController::class)->except('show');
```

Requests require an active product type, optional active craft, and optional arrays of active employees with exact position codes. Enforce one active configuration per product type. Sync the typed assignment pivot for order, artwork, and procurement employees. Do not overwrite legacy processor text or single-ID compatibility columns. Save settlement separately.

- [ ] **Step 4: Build compact list and form views**

List columns: product type, craft path, three processor groups, settlement, template, actions. Form product type is searchable but not creatable. Craft uses hierarchical rendering. Processor controls are searchable multi-selects and list every assigned employee with `、`. Settlement uses creatable Tom Select with `月结` and `周结` defaults.

- [ ] **Step 5: Run tests and commit**

```powershell
php artisan test tests\Feature\OrderProcessingCrudTest.php
git add app/Http/Controllers/OrderProcessingController.php app/Http/Requests
git add routes/web.php resources/views/business/order-processing tests/Feature/OrderProcessingCrudTest.php
git commit -m "feat: add order processing configuration management"
```

## Task 8: Build Craft Hierarchy CRUD and Inline Quick Create

**Files:**
- Create: `app/Http/Controllers/ProcessingCraftController.php`
- Create: `app/Http/Requests/{StoreProcessingCraft,UpdateProcessingCraft}Request.php`
- Create: `resources/views/business/processing-crafts/{index,form,_quick-create-dialog}.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/js/admin-ui.js`
- Test: `tests/Feature/ProcessingCraftCrudTest.php`

- [ ] **Step 1: Write failing tests**

Cover unlimited parent depth, path generation, search by name/path, cycle prevention, JSON quick-create response `{id, name, path, depth}`, soft delete, blocked deletion with children, and blocked deletion when referenced by order processing.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\ProcessingCraftCrudTest.php
```

- [ ] **Step 3: Add routes and controller**

```php
Route::resource('processing-crafts', ProcessingCraftController::class)->except('show');
Route::post('processing-crafts/quick-create', [ProcessingCraftController::class, 'quickStore'])
    ->name('processing-crafts.quick-store');
```

Path generation is centralized in a private/service method and updates descendants in a transaction when a parent/name changes. Update rejects selecting self or descendants as parent.

- [ ] **Step 4: Build the independent management page**

Use UI terms “工艺层级、工艺项、工艺名称、上级工艺、工艺路径”. Accept an optional safe `return_to` route name/query and render `返回订单处理配置` when present.

- [ ] **Step 5: Implement inline creation behavior**

The order-processing form dialog posts JSON. On success call:

```js
window.AdminUI.addSelectOption(craftSelect, {
    value: response.id,
    text: response.path,
    depth: response.depth,
}, true);
```

On 422, keep the dialog open and render Chinese validation errors.

- [ ] **Step 6: Run tests, compile, and commit**

```powershell
php artisan test tests\Feature\ProcessingCraftCrudTest.php
npm run dev
git add app/Http/Controllers/ProcessingCraftController.php app/Http/Requests
git add routes/web.php resources/views/business/processing-crafts resources/js/admin-ui.js
git add tests/Feature/ProcessingCraftCrudTest.php public/js/app.js mix-manifest.json
git commit -m "feat: add craft hierarchy management"
```

## Task 9: Build Employee and Position Permission Management

**Files:**
- Create: `app/Http/Controllers/{Employee,Position}Controller.php`
- Create: `app/Http/Requests/{StoreEmployee,UpdateEmployee,StorePosition,UpdatePosition}Request.php`
- Create: `resources/views/employees/{index,form}.blade.php`
- Create: `resources/views/positions/{index,form}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/EmployeePositionCrudTest.php`

- [ ] **Step 1: Write failing tests**

Cover employee name/company/supervisor/account/positions, multi-position sync, company-name autocomplete values, soft delete exclusion from business dropdowns, position CRUD, manager access, and delegation rejection for unowned/non-delegable permissions.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\EmployeePositionCrudTest.php
```

- [ ] **Step 3: Implement controllers and requests**

Routes use `employees` and `positions` resources. Employee store/update uses a transaction and `positions()->sync($validated['position_ids'])`. Position update intersects submitted permissions with `PermissionService::delegableFor($actor)` before sync; unauthorized IDs produce 422 instead of silent removal.

- [ ] **Step 4: Build two-tab pages**

Tabs: `员工档案` and `职位权限`. Employee form has searchable company-name input with suggestions but permits new text, searchable supervisor/account selects, and multi-position select. Position form groups permissions by business area and uses checkboxes.

- [ ] **Step 5: Run tests and commit**

```powershell
php artisan test tests\Feature\EmployeePositionCrudTest.php
git add app/Http/Controllers/EmployeeController.php app/Http/Controllers/PositionController.php
git add app/Http/Requests routes/web.php resources/views/employees resources/views/positions
git add tests/Feature/EmployeePositionCrudTest.php
git commit -m "feat: add employee and position permission management"
```

## Task 10: Separate Administrator Account Permission and Soft Delete

**Files:**
- Modify: `app/Http/Controllers/AdminManagementController.php`
- Modify: `resources/views/admins/{index,form,_tree}.blade.php`
- Modify: `app/Models/Admin.php`
- Test: `tests/Feature/AdminAccountManagementTest.php`

- [ ] **Step 1: Write failing account-policy tests**

Assert: super manages manager/employee; manager manages employee accounts; employee with `admin_accounts.manage` manages employee accounts; no non-super manages manager/super; delete soft-deletes and blocks login; account forms link an optional employee profile.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\AdminAccountManagementTest.php
```

- [ ] **Step 3: Replace role-string checks with Policy checks**

Controller methods call `$this->authorize(...)`. Keep the existing parent-admin hierarchy for display only; account authorization comes from `AdminPolicy` and `PermissionService`.

- [ ] **Step 4: Translate and update admin views**

Use Chinese labels `超级管理员`, `管理员`, `员工`, `启用`, `停用`, `编辑`, `删除`. Replace browser `confirm()` with the shared delete dialog. Destroy calls `$targetAdmin->delete()` and never hard-deletes.

- [ ] **Step 5: Run tests and commit**

```powershell
php artisan test tests\Feature\AdminAccountManagementTest.php
git add app/Http/Controllers/AdminManagementController.php app/Models/Admin.php resources/views/admins
git add tests/Feature/AdminAccountManagementTest.php
git commit -m "feat: separate administrator account permissions"
```

## Task 11: Complete Existing-Page Chinese Localization

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/dashboard/index.blade.php`
- Modify: `resources/views/data-processing/index.blade.php`
- Modify: `resources/views/orders/index.blade.php`
- Modify: `resources/views/reports/{index,form,schedule}.blade.php`
- Modify: user-visible messages in related controllers
- Test: `tests/Feature/ExistingPagesChineseLocalizationTest.php`

- [ ] **Step 1: Write failing localization assertions**

Request each reachable page and assert Chinese title, field labels, buttons, status text, empty-state text, and navigation. Assert obsolete English UI strings such as `Dashboard`, `Logout`, `Delete`, `Download All`, and `Admin Management` are absent from rendered pages.

- [ ] **Step 2: Run the focused baseline test when useful**

```powershell
php artisan test tests\Feature\ExistingPagesChineseLocalizationTest.php
```

- [ ] **Step 3: Translate views and controller flash messages**

Use consistent vocabulary:

```text
Dashboard -> 工作台
Data Processing -> 数据处理
Admin Management -> 管理员管理
Process File -> 处理文件
Processing/Completed/Failed -> 处理中/已完成/失败
Download/Delete/Edit/Create/Cancel -> 下载/删除/编辑/新增/取消
```

Move orders/reports pages onto `layouts.app` where reachable. Keep route names and database fields English.

- [ ] **Step 4: Run tests and commit**

```powershell
php artisan test tests\Feature\ExistingPagesChineseLocalizationTest.php
git add resources/views app/Http/Controllers
git add tests/Feature/ExistingPagesChineseLocalizationTest.php
git commit -m "feat: localize admin interface in Chinese"
```

## Task 12: Migrate Real Data, Create qxadmin, and Verify End to End

**Files:**
- Runtime backup: `storage/app/private/backups/database-before-business-admin-<timestamp>.sqlite`
- Runtime logs: `storage/logs/business-admin-setup-<timestamp>.log`
- Create: `playwright.config.js`
- Test: `tests/browser/business-admin.spec.js`

- [ ] **Step 1: Run all focused tests fresh**

```powershell
php artisan test tests\Feature\BusinessAdminSchemaTest.php
php artisan test tests\Feature\EmployeePermissionSchemaTest.php
php artisan test tests\Feature\BusinessDataBackfillServiceTest.php
php artisan test tests\Unit\PermissionServiceTest.php
php artisan test tests\Feature\BusinessAuthorizationTest.php
php artisan test tests\Feature\SkuProductTypeCrudTest.php
php artisan test tests\Feature\OrderProcessingCrudTest.php
php artisan test tests\Feature\ProcessingCraftCrudTest.php
php artisan test tests\Feature\EmployeePositionCrudTest.php
php artisan test tests\Feature\AdminAccountManagementTest.php
php artisan test tests\Feature\ExistingPagesChineseLocalizationTest.php
```

Expected: every focused suite passes. Do not run `php artisan test` without paths.

- [ ] **Step 2: Back up the local SQLite database**

Verify `DB_CONNECTION=sqlite` and `DB_DATABASE` points to `database/database.sqlite`, then copy it to a timestamped path under `storage/app/private/backups`. Print and verify source/destination byte sizes. Do not delete old backups.

- [ ] **Step 3: Apply additive migrations and setup data**

```powershell
php artisan migrate
php artisan db:seed --class=BusinessAdminSetupSeeder
php artisan admin:bootstrap-qxadmin
```

Enter the user-provided qxadmin password only at the secret prompt. Capture migration/seeder output with `Tee-Object` to the named log. Never run `migrate:fresh`, `migrate:refresh`, or truncate commands.

- [ ] **Step 4: Verify database invariants**

Use read-only queries to assert:

- 3,928 active SKU mappings remain;
- 318 active order-processing configurations remain;
- 64 active craft items remain;
- every active SKU and order configuration has a product type ID;
- no fake blank SKU was created;
- employee names are unique after normalization;
- unresolved legacy values are exactly the reported exceptions;
- qxadmin is active, role manager, parent Admin, and linked to an employee;
- template and template-description values were not overwritten.

- [ ] **Step 5: Add and run Playwright UI verification**

The spec logs in through `/login`, then checks desktop `1440x900` and mobile `390x844` for:

- Chinese navigation and titles;
- SKU/product-type tabs;
- order-processing filters and hierarchical craft selector;
- quick-create craft dialog without page reload;
- delete confirmation dialog;
- employee/position tabs;
- no horizontal text overlap or clipped action buttons.

Create `playwright.config.js`:

```js
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/browser',
    use: {
        baseURL: 'http://127.0.0.1:8011',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8011',
        url: 'http://127.0.0.1:8011/login',
        reuseExistingServer: false,
        timeout: 120000,
    },
});
```

`business-admin.spec.js` reads `process.env.QXADMIN_TEST_PASSWORD` and throws a clear error when it is absent. Do not hardcode the password.

Run:

```powershell
$env:QXADMIN_TEST_PASSWORD = Read-Host '请输入 qxadmin 测试密码'
npx playwright test tests/browser/business-admin.spec.js
Remove-Item Env:QXADMIN_TEST_PASSWORD
```

Expected: PASS with screenshots saved only to ignored Playwright output directories.

- [ ] **Step 6: Final diff and commit**

```powershell
git diff --check
git status --short
git add playwright.config.js tests/browser/business-admin.spec.js
git commit -m "test: verify business admin workflows"
```

Confirm private files, SQLite databases, backups, logs, `outputs/`, and `scripts/__pycache__/` are not staged.
