# SKU Bulk Editing And Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add same-cleaned-SKU bulk editing, one-click SKU cleaning, and consistent configurable pagination to the SKU/product-type and order-processing administration pages.

**Architecture:** Keep the existing resource controllers and add two narrowly scoped POST endpoints to `SkuMatchProductTypeController`. Use a dedicated FormRequest for server-side bulk validation, a page-specific native dialog driven by `admin-ui.js`, and one reusable Blade pagination component backed by a small per-page whitelist helper.

**Tech Stack:** Laravel 8, Eloquent, Blade, native HTML dialog, Tom Select, vanilla JavaScript, PHPUnit with isolated SQLite.

---

### Task 1: Bulk Update Endpoint

**Files:**
- Create: `app/Http/Requests/BulkUpdateSkuMatchProductTypeRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/SkuMatchProductTypeController.php`
- Test: `tests/Feature/SkuProductTypeCrudTest.php`

- [ ] **Step 1: Add focused bulk-update tests**

Add tests that create two active mappings sharing one `cleaned_sku`, submit their IDs, and assert both product-type and lister snapshots change. Add rejection cases for mixed `cleaned_sku`, missing/deleted IDs, duplicate IDs, an ineligible lister, and an actor without `sku_product_types.manage`.

```php
$response = $this->actingAs($actor, 'admin')
    ->post(route('sku-product-types.bulk-update'), [
        'sku_ids' => [$first->id, $second->id],
        'product_type_id' => $newType->id,
        'product_lister_employee_id' => $newLister->id,
        'return_query' => [
            'tab' => 'skus',
            'search' => 'SHARED-CLEAN',
            'sku_page' => 2,
            'sku_per_page' => 20,
        ],
    ]);

$response->assertRedirect(route('sku-product-types.index', [
    'tab' => 'skus',
    'search' => 'SHARED-CLEAN',
    'sku_page' => 2,
    'sku_per_page' => 20,
]));
```

- [ ] **Step 2: Add the request validator**

Validate `sku_ids` as a distinct array with 2-100 active records, require an active product type, reuse the existing advertising/operations employee eligibility query for the optional lister, and allow only known return-query keys.

```php
return [
    'sku_ids' => ['required', 'array', 'min:2', 'max:100'],
    'sku_ids.*' => [
        'required',
        'integer',
        'distinct',
        Rule::exists('sku_match_product_type', 'id')->whereNull('deleted_at'),
    ],
    'product_type_id' => [
        'required',
        'integer',
        Rule::exists('product_types', 'id')->whereNull('deleted_at'),
    ],
    'product_lister_employee_id' => ['nullable', 'integer', $this->eligibleListerRule()],
    'return_query' => ['nullable', 'array'],
    'return_query.tab' => ['nullable', Rule::in(['skus'])],
    'return_query.search' => ['nullable', 'string', 'max:255'],
    'return_query.product_type_id' => ['nullable', 'integer'],
    'return_query.sku_page' => ['nullable', 'integer', 'min:1'],
    'return_query.sku_per_page' => ['nullable', Rule::in([20, 50, 100])],
];
```

- [ ] **Step 3: Implement the transaction and route**

Register the named route before the resource route so it cannot be consumed as a resource ID. In the controller, load all IDs, authorize every model, reject more than one distinct `cleaned_sku`, resolve the new snapshots once, and update all selected rows inside one transaction.

```php
Route::post('sku-product-types/bulk-update', [SkuMatchProductTypeController::class, 'bulkUpdate'])
    ->name('sku-product-types.bulk-update');
```

```php
$skuMatches = SkuMatchProductType::query()
    ->whereIn('id', $validated['sku_ids'])
    ->get();

foreach ($skuMatches as $skuMatch) {
    $this->authorize('update', $skuMatch);
}

if ($skuMatches->pluck('cleaned_sku')->unique()->count() !== 1) {
    throw ValidationException::withMessages([
        'sku_ids' => '只能批量修改清洗后 SKU 完全相同的记录。',
    ]);
}

DB::transaction(function () use ($skuMatches, $snapshot) {
    SkuMatchProductType::query()
        ->whereIn('id', $skuMatches->pluck('id'))
        ->update($snapshot);
});
```

- [ ] **Step 4: Run the focused test file**

Run: `php artisan test tests/Feature/SkuProductTypeCrudTest.php`

Expected: all tests pass against the SQLite testing database configured by PHPUnit.

- [ ] **Step 5: Commit the backend batch**

```bash
git add app/Http/Requests/BulkUpdateSkuMatchProductTypeRequest.php app/Http/Controllers/SkuMatchProductTypeController.php routes/web.php tests/Feature/SkuProductTypeCrudTest.php
git commit -m "feat: add grouped SKU bulk updates"
```

### Task 2: Bulk Edit Dialog

**Files:**
- Create: `resources/views/components/sku-bulk-edit-dialog.blade.php`
- Modify: `resources/views/business/sku-product-types/index.blade.php`
- Modify: `resources/views/business/sku-product-types/_sku-table.blade.php`
- Modify: `resources/js/admin-ui.js`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/SkuProductTypeCrudTest.php`

- [ ] **Step 1: Render authorized bulk-edit controls**

Add a checkbox column for actors allowed to update SKU mappings, a selected-count toolbar with “全选同组” and “批量修改”, and a dialog containing searchable product-type and lister selects. Pass `eligibleListers` from the index controller and render current query values as `return_query[...]` hidden inputs.

```blade
<input type="checkbox"
       value="{{ $skuMatch->id }}"
       data-sku-bulk-checkbox
       data-cleaned-sku="{{ $skuMatch->cleaned_sku }}">
```

```blade
<x-sku-bulk-edit-dialog
    :product-types="$typeOptions"
    :eligible-listers="$eligibleListers" />
```

- [ ] **Step 2: Implement current-page selection behavior**

Add `initializeSkuBulkEditor()` to `admin-ui.js`. The first selected row establishes the allowed `cleaned_sku`; mismatched rows are rejected with visible feedback. “全选同组” selects matching visible rows, and opening the dialog creates one hidden `sku_ids[]` input per selected row.

```js
function selectedSkuRows(root) {
    return Array.from(root.querySelectorAll('[data-sku-bulk-checkbox]:checked'));
}

function selectionGroup(rows) {
    return rows.length ? rows[0].dataset.cleanedSku : '';
}
```

Initialize the dialog's Tom Select controls through the existing `initializeSearchableSelects(dialog)` helper and clear generated hidden inputs when the dialog closes.

- [ ] **Step 3: Add restrained dialog styling**

Reuse the existing confirmation-dialog visual language: maximum width around 560px, 8px radius, opaque white surface, readable backdrop, and stacked mobile actions. Do not introduce a nested card.

- [ ] **Step 4: Assert the controls and query preservation**

Extend the feature test to assert authorized actors see the bulk route, dialog fields, row IDs, and return query inputs; read-only actors must not see bulk mutation controls.

- [ ] **Step 5: Build assets and run the focused test**

Run: `npm run development`

Run: `php artisan test tests/Feature/SkuProductTypeCrudTest.php`

Expected: Mix completes and the focused feature tests pass.

- [ ] **Step 6: Commit the dialog batch**

```bash
git add resources/views/components/sku-bulk-edit-dialog.blade.php resources/views/business/sku-product-types/index.blade.php resources/views/business/sku-product-types/_sku-table.blade.php resources/js/admin-ui.js resources/css/app.css public/js/app.js public/css/app.css public/mix-manifest.json tests/Feature/SkuProductTypeCrudTest.php app/Http/Controllers/SkuMatchProductTypeController.php
git commit -m "feat: add SKU bulk edit dialog"
```

### Task 3: One-Click SKU Cleaning

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/SkuMatchProductTypeController.php`
- Modify: `resources/views/business/sku-product-types/form.blade.php`
- Modify: `resources/js/admin-ui.js`
- Test: `tests/Feature/SkuProductTypeCrudTest.php`

- [ ] **Step 1: Add endpoint tests**

Use temporary exclude-value and pattern JSON fixtures through a mocked `SkuCleaningService` binding or constructor fixture paths. Assert a manager receives `{"cleaned_sku":"CS-QK1000-CX"}`, blank input returns 422, and a viewer without management permission receives 403.

- [ ] **Step 2: Add the protected JSON endpoint**

```php
Route::post('sku-product-types/clean-sku', [SkuMatchProductTypeController::class, 'cleanSku'])
    ->name('sku-product-types.clean-sku');
```

```php
public function cleanSku(Request $request, SkuCleaningService $cleaner)
{
    $this->authorize('create', SkuMatchProductType::class);
    $validated = $request->validate([
        'original_sku' => ['required', 'string', 'max:255'],
    ]);

    return response()->json([
        'cleaned_sku' => $cleaner->cleanSkuUsingValuesAndPatterns($validated['original_sku']),
    ]);
}
```

- [ ] **Step 3: Add the create-form button and fetch behavior**

Only render the button when `$editing` is false. Put it beside `original_sku`, target `cleaned_sku`, disable it while fetching, and display a local error without submitting the form.

```blade
<button type="button"
        class="button button-secondary"
        data-sku-clean-trigger
        data-clean-url="{{ route('sku-product-types.clean-sku') }}">
    清洗 SKU
</button>
```

- [ ] **Step 4: Build and verify**

Run: `npm run development`

Run: `php artisan test tests/Feature/SkuProductTypeCrudTest.php`

Expected: asset build and SKU feature tests pass.

- [ ] **Step 5: Commit the cleaning batch**

```bash
git add routes/web.php app/Http/Controllers/SkuMatchProductTypeController.php resources/views/business/sku-product-types/form.blade.php resources/js/admin-ui.js public/js/app.js public/mix-manifest.json tests/Feature/SkuProductTypeCrudTest.php
git commit -m "feat: clean SKU values from the create form"
```

### Task 4: Shared Pagination Controls

**Files:**
- Create: `app/Support/PerPageOptions.php`
- Create: `resources/views/components/business-pagination.blade.php`
- Modify: `app/Http/Controllers/SkuMatchProductTypeController.php`
- Modify: `app/Http/Controllers/OrderProcessingController.php`
- Modify: `resources/views/business/sku-product-types/_sku-table.blade.php`
- Modify: `resources/views/business/sku-product-types/_type-table.blade.php`
- Modify: `resources/views/business/order-processing/index.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/SkuProductTypeCrudTest.php`
- Test: `tests/Feature/OrderProcessingCrudTest.php`

- [ ] **Step 1: Add pagination assertions**

Assert defaults remain 50, valid values `20`, `50`, and `100` are respected, invalid values fall back to 50, target page parameters select the expected records, and search/filter parameters appear in generated links. Cover `sku_page`/`sku_per_page`, `type_page`/`type_per_page`, and `page`/`per_page` separately.

- [ ] **Step 2: Add a small whitelist helper**

```php
final class PerPageOptions
{
    public const ALLOWED = [20, 50, 100];

    public static function resolve(Request $request, $parameter, $default = 50)
    {
        $value = (int) $request->query($parameter, $default);

        return in_array($value, self::ALLOWED, true) ? $value : $default;
    }
}
```

- [ ] **Step 3: Apply independent paginator names**

Use the helper in both controllers and preserve existing page names.

```php
$skuPerPage = PerPageOptions::resolve($request, 'sku_per_page');
$typePerPage = PerPageOptions::resolve($request, 'type_per_page');
$perPage = PerPageOptions::resolve($request, 'per_page');
```

- [ ] **Step 4: Build the reusable Blade component**

The component must render for any non-empty result set, even when there is only one page, so the per-page selector remains available. It displays total count, per-page selector, previous/next links, current/last page, and a bounded numeric jump input. Hidden inputs preserve all query parameters except the component's own page and per-page keys.

```blade
<x-business-pagination
    :paginator="$skuMatches"
    page-name="sku_page"
    per-page-name="sku_per_page" />
```

- [ ] **Step 5: Replace all three hand-written pagers**

Use the same component in the SKU table, product-type table, and order-processing index. Keep labels specific through an `aria-label` prop.

- [ ] **Step 6: Run focused tests and build assets**

Run: `php artisan test tests/Feature/SkuProductTypeCrudTest.php tests/Feature/OrderProcessingCrudTest.php`

Run: `npm run development`

Expected: both test files pass and Mix completes.

- [ ] **Step 7: Commit the pagination batch**

```bash
git add app/Support/PerPageOptions.php resources/views/components/business-pagination.blade.php app/Http/Controllers/SkuMatchProductTypeController.php app/Http/Controllers/OrderProcessingController.php resources/views/business/sku-product-types/_sku-table.blade.php resources/views/business/sku-product-types/_type-table.blade.php resources/views/business/order-processing/index.blade.php resources/css/app.css public/css/app.css public/mix-manifest.json tests/Feature/SkuProductTypeCrudTest.php tests/Feature/OrderProcessingCrudTest.php
git commit -m "feat: add configurable business pagination"
```

### Task 5: Verification And Missing-Data Report

**Files:**
- Verify only; no production data writes.

- [ ] **Step 1: Confirm PHPUnit database isolation**

Inspect `phpunit.xml` and confirm the test connection is SQLite `:memory:` before running any tests. Stop if it can fall back to `.env`.

- [ ] **Step 2: Run the safe regression set**

Run: `php artisan test tests/Feature/SkuProductTypeCrudTest.php tests/Feature/OrderProcessingCrudTest.php tests/Feature/BusinessAuthorizationTest.php`

Expected: all selected tests pass without touching `database/database.sqlite`.

- [ ] **Step 3: Perform browser verification**

Start the development server if needed and verify desktop plus mobile widths: same-group selection, mismatch feedback, dialog open/close, searchable selects, one-click cleaning, per-page changes, and page jump. Confirm controls do not overlap and tables remain horizontally scrollable on mobile.

- [ ] **Step 4: Query missing data read-only**

Run direct read-only SQLite queries against the current local database:

```sql
SELECT sku.original_sku, sku.cleaned_sku, sku.chinese_name
FROM sku_match_product_type sku
LEFT JOIN product_types pt
  ON pt.id = sku.product_type_id AND pt.deleted_at IS NULL
WHERE sku.deleted_at IS NULL
  AND (
    sku.product_type_id IS NULL OR pt.id IS NULL
    OR sku.chinese_name IS NULL OR TRIM(sku.chinese_name) = ''
  )
ORDER BY sku.original_sku;

SELECT pt.chinese_name
FROM product_types pt
LEFT JOIN sku_match_product_type sku
  ON sku.product_type_id = pt.id AND sku.deleted_at IS NULL
WHERE pt.deleted_at IS NULL AND sku.id IS NULL
ORDER BY pt.chinese_name;
```

Report both counts and all names directly in the final response; do not create an Excel file.

- [ ] **Step 5: Review the final diff**

Run: `git diff --check`

Run: `git status --short`

Confirm `outputs/`, `scripts/__pycache__/`, private image directories, and the multi-website SKU option scraping work are not staged.
