# Order Processing Multi-Assignee Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single-employee order, artwork, and procurement processor links with equal-rank multi-employee assignments while preserving legacy data.

**Architecture:** Add one typed assignment pivot table shared by all three processor categories. Existing single-employee columns remain compatibility snapshots, while models, backfill code, and the future CRUD page use the pivot relations as the authoritative source.

**Tech Stack:** Laravel 8, PHP 7.3, Eloquent, SQLite/MySQL-compatible migrations, PHPUnit 9, Blade, Tom Select 2.4.3.

**Execution Note:** The user waived mandatory pre-implementation RED runs to reduce token and subagent usage. Tests still must be written and run before each commit, and the local database must never be used by tests.

---

## File Structure

- `database/migrations/2026_06_28_000001_create_product_processing_craft_employee_assignment_table.php`: creates the typed pivot and migrates existing single-ID links.
- `app/Models/ProductProcessingCraft.php`: exposes the three filtered many-to-many employee relations.
- `app/Models/Employee.php`: exposes the reverse order-processing configuration relation.
- `app/Services/BusinessDataBackfillService.php`: writes legacy name matches to both compatibility columns and the typed pivot.
- `tests/Feature/ProductProcessingCraftMultiAssigneeTest.php`: verifies relation isolation, uniqueness, and compatibility-column behavior.
- `tests/Feature/BusinessDataBackfillServiceTest.php`: verifies idempotent legacy backfill into the pivot.
- Original plan Task 7: implements multi-select validation, persistence, and display in the order-processing CRUD page.

## Task 1: Add the Multi-Assignee Data Foundation

**Files:**
- Create: `database/migrations/2026_06_28_000001_create_product_processing_craft_employee_assignment_table.php`
- Modify: `app/Models/ProductProcessingCraft.php`
- Modify: `app/Models/Employee.php`
- Modify: `app/Services/BusinessDataBackfillService.php`
- Create: `tests/Feature/ProductProcessingCraftMultiAssigneeTest.php`
- Modify: `tests/Feature/BusinessDataBackfillServiceTest.php`

- [ ] **Step 1: Add focused test coverage**

Create tests that use `RefreshDatabase` and explicitly create employees and positions. Cover:

```php
$craft->orderProcessorEmployees()->sync([
    $orderEmployeeA->id => ['assignment_type' => 'order_processing'],
    $orderEmployeeB->id => ['assignment_type' => 'order_processing'],
]);
$craft->artworkProcessorEmployees()->sync([
    $artworkEmployee->id => ['assignment_type' => 'artwork_processing'],
]);

$this->assertCount(2, $craft->fresh()->orderProcessorEmployees);
$this->assertCount(1, $craft->fresh()->artworkProcessorEmployees);
$this->assertCount(0, $craft->fresh()->procurementProcessorEmployees);
```

Also assert the unique triple rejects duplicate assignments, the same employee can be assigned to two different types, and syncing pivot assignments does not overwrite the three legacy single-ID or text columns.

Extend the backfill test so a legacy order/artwork/procurement name creates one row of each type, and a second `run()` leaves pivot counts and timestamps unchanged.

- [ ] **Step 2: Create the additive migration**

Create the table with explicit short index and foreign-key names so MySQL's identifier limit is respected:

```php
Schema::create('product_processing_craft_employee_assignment', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('product_processing_craft_id');
    $table->unsignedBigInteger('employee_id');
    $table->string('assignment_type', 40);
    $table->timestamps();

    $table->unique(
        ['product_processing_craft_id', 'employee_id', 'assignment_type'],
        'ppc_employee_assignment_unique'
    );
    $table->index(
        ['assignment_type', 'product_processing_craft_id'],
        'ppc_assignment_type_lookup'
    );
    $table->index(
        ['employee_id', 'assignment_type'],
        'ppc_employee_type_lookup'
    );

    $table->foreign('product_processing_craft_id', 'ppc_assignment_craft_fk')
        ->references('id')->on('product_processing_craft')->onDelete('cascade');
    $table->foreign('employee_id', 'ppc_assignment_employee_fk')
        ->references('id')->on('employees')->onDelete('restrict');
});
```

After table creation, iterate existing `product_processing_craft` rows in ID chunks. Insert each non-null compatibility ID with its matching type through `insertOrIgnore()`. Preserve all source columns. `down()` drops only the new table.

- [ ] **Step 3: Add the filtered model relations**

Add these methods to `ProductProcessingCraft`:

```php
public function orderProcessorEmployees()
{
    return $this->belongsToMany(Employee::class, 'product_processing_craft_employee_assignment')
        ->withPivot('assignment_type')
        ->wherePivot('assignment_type', 'order_processing')
        ->withTimestamps();
}
```

Add equivalent `artworkProcessorEmployees()` and `procurementProcessorEmployees()` methods with `artwork_processing` and `procurement`.

Add a reverse `processingCraftAssignments()` relation to `Employee` for history display. Do not remove the existing single-employee relations in this phase.

- [ ] **Step 4: Extend the legacy backfill**

When `BusinessDataBackfillService` resolves each processing employee, retain the existing compatibility-ID assignment and add the typed pivot without detaching other assignments:

```php
$processingCraft->orderProcessorEmployees()->syncWithoutDetaching([
    $orderEmployee->id => ['assignment_type' => 'order_processing'],
]);
```

Apply the same behavior for artwork and procurement. Skip null/unresolved employees. Existing pivot rows and timestamps must remain unchanged on rerun.

- [ ] **Step 5: Run isolated verification**

Run separately:

```powershell
php artisan test tests\Feature\ProductProcessingCraftMultiAssigneeTest.php
php artisan test tests\Feature\BusinessDataBackfillServiceTest.php
php artisan test tests\Feature\EmployeePermissionSchemaTest.php
```

Expected: all tests pass using `DB_DATABASE=:memory:`. Do not run migrations or seeders against `database/database.sqlite`.

- [ ] **Step 6: Commit**

```powershell
git add database/migrations/2026_06_28_000001_create_product_processing_craft_employee_assignment_table.php
git add app/Models/ProductProcessingCraft.php app/Models/Employee.php
git add app/Services/BusinessDataBackfillService.php
git add tests/Feature/ProductProcessingCraftMultiAssigneeTest.php
git add tests/Feature/BusinessDataBackfillServiceTest.php
git commit -m "feat: support multiple order processing assignees"
```

## Task 2: Apply Multi-Select Behavior in Order Processing CRUD

**Files:**
- Create: `app/Http/Controllers/OrderProcessingController.php`
- Create: `app/Http/Requests/StoreOrderProcessingRequest.php`
- Create: `app/Http/Requests/UpdateOrderProcessingRequest.php`
- Create: `resources/views/business/order-processing/index.blade.php`
- Create: `resources/views/business/order-processing/form.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrderProcessingCrudTest.php`

- [ ] **Step 1: Add focused CRUD tests**

Test authorized create/update with two employees in one category, independent assignments in all three categories, role-filtered candidates, rejection of inactive/deleted/wrong-position employees, list display of every assigned name, soft deletion, and preservation of legacy text and single-ID fields.

- [ ] **Step 2: Validate arrays and eligible employees**

The two Form Requests accept:

```php
'order_processor_employee_ids' => ['nullable', 'array'],
'order_processor_employee_ids.*' => ['integer', 'distinct'],
'artwork_processor_employee_ids' => ['nullable', 'array'],
'artwork_processor_employee_ids.*' => ['integer', 'distinct'],
'procurement_processor_employee_ids' => ['nullable', 'array'],
'procurement_processor_employee_ids.*' => ['integer', 'distinct'],
```

After base validation, verify every submitted employee is active, not soft-deleted, and has the matching active position code. Return Chinese validation errors for invalid candidates.

- [ ] **Step 3: Sync typed assignments without touching snapshots**

Authorize with `order_processing.manage`, save product type/craft/settlement/template fields, then sync each filtered relation with explicit `assignment_type` pivot values. An empty array removes only that type's assignments. Do not update `order_processor`, `artwork_processor`, `procurement_processor`, or the three compatibility employee-ID fields.

- [ ] **Step 4: Build multi-select views**

Use three searchable Tom Select controls with `multiple`, grouped by the exact eligible position. The list page eager-loads the three relations and joins names with `、`. Disabled or soft-deleted historical assignees remain visible on existing records but are not selectable for new assignments.

- [ ] **Step 5: Run and commit**

```powershell
php artisan test tests\Feature\OrderProcessingCrudTest.php
npm run dev
git add app/Http/Controllers/OrderProcessingController.php app/Http/Requests
git add routes/web.php resources/views/business/order-processing
git add tests/Feature/OrderProcessingCrudTest.php public/js/app.js public/css/app.css mix-manifest.json
git commit -m "feat: add multi-assignee order processing management"
```

Expected: focused tests pass and Mix exits 0.

