# Clothing Template Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement three requested template-specific rules, add product links to every business export, and register independent templates for every uncovered business sheet in `all-clothings-factory.xlsx`.

**Architecture:** Keep specialized behavior in the existing concrete templates. Add a small static-header template base for 14 new independent classes, retain the existing common option-rule engine, and pass the source sales link through `DataProcessingService` into the shared audit columns.

**Tech Stack:** Laravel/PHP 7.3, PHPExcel, PHPUnit, private reference workbook used only during development.

---

### Task 1: Implement The Three Specialized Rules First

**Files:**
- Modify: `app/Services/OrderExportTemplates/StyleImageHeatTransferTemplate.php`
- Modify: `app/Services/OrderExportTemplates/CtcxTemplate.php`
- Modify: `app/Services/OrderExportTemplates/PetOutlineColorTemplate.php`
- Modify: `tests/Unit/OrderExportTemplates/StyleImageHeatTransferTemplateTest.php`
- Modify: `tests/Unit/OrderExportTemplates/CtcxTemplateTest.php`
- Modify: `tests/Unit/OrderExportTemplates/PetOutlineColorTemplateTest.php`

- [ ] **Step 1: Add focused test cases without a separate red-test run**

Add tests alongside the implementation, following the user's instruction to omit the preliminary failing run.

```php
public function test_choose_style_resolves_image_into_design_style()
{
    // Resolver returns /images/style.png only for Choose Style: Vintage.
    // Assert 设计风格 equals that local path.
}

public function test_upload_your_icon_uses_following_side_specific_upload_value()
{
    // Choose Icon on Left Sleeve: Upload Your Icon
    // Upload Your Icon on Left Sleeve: https://example.test/left.png
    // Repeat for right and assert both icon columns contain the resolved paths.
}

public function test_text_under_photo_maps_to_first_photo_caption()
{
    // Assert Text Under the Photo: Always Loved maps to 图片1下方文字.
}
```

- [ ] **Step 2: Extend style-image matching**

Treat exact `Choose Style` as an image-backed design-style option in addition to names containing `design`.

```php
$isDesignStyle = strpos($lowerName, 'design') !== false
    || strcasecmp($name, 'Choose Style') === 0;

if ($isDesignStyle) {
    $imagePath = $this->resolveOptionImage($context, $row, $name, $value);
    $this->setHeaderValue($values, '设计风格', $imagePath !== '' ? $imagePath : $value);
}
```

- [ ] **Step 3: Resolve uploaded sleeve icons in CTCX**

Iterate attributes with their index. When a side-specific choose-icon option has value `Upload Your Icon`, inspect the following attribute only, require its name to contain `Upload Your Icon`, the same side, and `Sleeve`, then resolve or retain its value. Use `setHeaderValue()` so any placeholder previously produced by the common rule is replaced.

```php
if ($this->isUploadIconChoice($lowerName, $value, 'left')) {
    $uploaded = $this->uploadedSleeveIconAfter($attributes, $index, 'left', $row, $context);
    if ($uploaded !== '') {
        $this->setHeaderValue($values, '左袖图标', $uploaded);
    }
}
```

- [ ] **Step 4: Map the pet photo caption**

Read `Text Under the Photo` case-insensitively from all attributes and write its nonblank value to `图片1下方文字`.

```php
$caption = $this->firstExactAttributeValue($allAttributes, 'Text Under the Photo');
if ($caption !== '') {
    $this->setHeaderValue($values, '图片1下方文字', $caption);
}
```

- [ ] **Step 5: Verify and commit the specialized-rule checkpoint**

Run each file separately because this Artisan version only reliably reports one supplied path:

```powershell
php artisan test tests/Unit/OrderExportTemplates/StyleImageHeatTransferTemplateTest.php
php artisan test tests/Unit/OrderExportTemplates/CtcxTemplateTest.php
php artisan test tests/Unit/OrderExportTemplates/PetOutlineColorTemplateTest.php
```

Expected: all three files pass.

```bash
git add app/Services/OrderExportTemplates/StyleImageHeatTransferTemplate.php app/Services/OrderExportTemplates/CtcxTemplate.php app/Services/OrderExportTemplates/PetOutlineColorTemplate.php tests/Unit/OrderExportTemplates/StyleImageHeatTransferTemplateTest.php tests/Unit/OrderExportTemplates/CtcxTemplateTest.php tests/Unit/OrderExportTemplates/PetOutlineColorTemplateTest.php docs/superpowers/plans/2026-07-02-clothing-template-expansion.md
git commit -m "feat: extend specialized clothing template rules"
```

### Task 2: Add Product Links To Shared Audit Columns

**Files:**
- Modify: `app/Services/OrderExportTemplates/AbstractOrderExportTemplate.php`
- Modify: `app/Services/DataProcessingService.php`
- Modify: `tests/Unit/OrderExportTemplates/TemplateHeadersTest.php`
- Modify: `tests/Unit/DataProcessingServiceTest.php`

- [ ] **Step 1: Append the fourth audit header and value**

```php
foreach (['产品规格', 'sku', 'cleaned_sku', '产品链接'] as $header) {
    if (!in_array($header, $headers, true)) {
        $headers[] = $header;
    }
}

$this->setHeaderValue($values, '产品链接', $row['product_link'] ?? '');
```

- [ ] **Step 2: Detect the source sales-link column**

Add `product_link` to `getSourceColumnIndices()`. Match Chinese `销售链接`/`产品链接` and normalized English `sales link`/`product link`; leave it null when absent.

```php
} elseif (
    strpos($rawHeaderValue, '销售链接') !== false
    || strpos($rawHeaderValue, '产品链接') !== false
    || strpos($headerValue, 'sales link') !== false
    || strpos($headerValue, 'product link') !== false
) {
    $indices['product_link'] = $column;
}
```

- [ ] **Step 3: Pass the source value to template rows**

```php
'product_link' => $sourceColumns['product_link'] === null
    ? ''
    : $this->getCellValue($sourceSheet, $sourceColumns['product_link'], $row),
```

- [ ] **Step 4: Update header and service tests**

Change existing expectations from three to four audit columns and add a fixture source column named `销售链接` whose value is asserted in the mapped template row.

- [ ] **Step 5: Run focused tests**

```powershell
php artisan test tests/Unit/OrderExportTemplates/TemplateHeadersTest.php
php artisan test tests/Unit/DataProcessingServiceTest.php
```

Expected: both files pass without writing the local database.

### Task 3: Create The Static Header Base And 14 Template Classes

**Files:**
- Create: `app/Services/OrderExportTemplates/StaticHeaderOrderExportTemplate.php`
- Create: `app/Services/OrderExportTemplates/FoamHoodieTemplate.php`
- Create: `app/Services/OrderExportTemplates/PatchworkHoodieTemplate.php`
- Create: `app/Services/OrderExportTemplates/HeatTransferPantsTemplate.php`
- Create: `app/Services/OrderExportTemplates/DigitalPrintTShirtTemplate.php`
- Create: `app/Services/OrderExportTemplates/DigitalPrintSetTemplate.php`
- Create: `app/Services/OrderExportTemplates/DigitalPrintHoodieTemplate.php`
- Create: `app/Services/OrderExportTemplates/AppliqueEmbroideryTemplate.php`
- Create: `app/Services/OrderExportTemplates/LineEmbroideryMomTemplate.php`
- Create: `app/Services/OrderExportTemplates/ThreeDimensionalEmbroideryTemplate.php`
- Create: `app/Services/OrderExportTemplates/DoubleSidedHoodieTemplate.php`
- Create: `app/Services/OrderExportTemplates/TowelEmbroideryTemplate.php`
- Create: `app/Services/OrderExportTemplates/HemBowEmbroideryTemplate.php`
- Create: `app/Services/OrderExportTemplates/CarEmbroideryTemplate.php`
- Create: `app/Services/OrderExportTemplates/DigitalPrintShortsTemplate.php`

- [ ] **Step 1: Add the metadata-only base**

```php
abstract class StaticHeaderOrderExportTemplate extends AbstractOrderExportTemplate
{
    protected $templateKey;
    protected $templateLabel;
    protected $chineseNames = [];
    protected $templateHeaders = [];

    public function key() { return $this->templateKey; }
    public function label() { return $this->templateLabel; }
    public function supportedChineseNames() { return $this->chineseNames; }
    public function headers() { return $this->withProductSpecsHeader($this->templateHeaders); }
}
```

- [ ] **Step 2: Create one independent class per source sheet**

Each class declares the exact first-row array from its source sheet, including duplicates. Use these keys and aliases:

| Class | Key | Label / supported names |
|---|---|---|
| `FoamHoodieTemplate` | `foam_hoodie` | `发泡卫衣` |
| `PatchworkHoodieTemplate` | `patchwork_hoodie` | `拼接卫衣` |
| `HeatTransferPantsTemplate` | `heat_transfer_pants` | `烫画裤子` |
| `DigitalPrintTShirtTemplate` | `digital_print_tshirt` | `数码印短袖`, `数码印T恤`, `数码印衬衫` |
| `DigitalPrintSetTemplate` | `digital_print_set` | `数码印套装` |
| `DigitalPrintHoodieTemplate` | `digital_print_hoodie` | `数码印卫衣` |
| `AppliqueEmbroideryTemplate` | `applique_embroidery` | `贴布绣`, `亮片贴布绣`, `亮片贴布绣文字刺绣` |
| `LineEmbroideryMomTemplate` | `line_embroidery_mom` | `线条刺绣妈妈款` |
| `ThreeDimensionalEmbroideryTemplate` | `three_dimensional_embroidery` | `立体绣` |
| `DoubleSidedHoodieTemplate` | `double_sided_hoodie` | `双面卫衣`, `双面卫衣-烫画`, `双面卫衣烫画` |
| `TowelEmbroideryTemplate` | `towel_embroidery` | `毛巾绣` |
| `HemBowEmbroideryTemplate` | `hem_bow_embroidery` | `下摆蝴蝶结刺绣` |
| `CarEmbroideryTemplate` | `car_embroidery` | `汽车刺绣` |
| `DigitalPrintShortsTemplate` | `digital_print_shorts` | `数码印短裤` |

Example concrete class shape:

```php
class FoamHoodieTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'foam_hoodie';
    protected $templateLabel = '发泡卫衣';
    protected $chineseNames = ['发泡卫衣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式',
        '衣服颜色', '尺码', '数量', '发泡颜色', '左袖信息', '左袖发泡符号',
        '左袖信息发泡颜色', '袖子位置', '右袖信息', '右袖发泡符号',
        '右袖信息发泡颜色', '袖子位置', '胸口信息字体', '胸口信息1',
        '胸口位置', '贺卡/包装',
    ];
}
```

- [ ] **Step 3: Preserve exact duplicate headers**

Do not deduplicate the two `产品图` columns in 数码印套装 or repeated text-color columns in 双面卫衣. Only the four audit columns are guarded against duplication by `withProductSpecsHeader()`.

### Task 4: Register Templates And Extend Common Header Aliases

**Files:**
- Modify: `app/Services/OrderExportTemplates/OrderExportTemplateRegistry.php`
- Modify: `app/Services/OrderExportTemplates/AbstractOrderExportTemplate.php`
- Create: `tests/Unit/OrderExportTemplates/ExpandedTemplateRegistryTest.php`
- Modify: `tests/Unit/OrderExportTemplates/TemplateHeadersTest.php`
- Modify: `tests/Unit/OrderExportTemplates/CommonOptionRulesTest.php`

- [ ] **Step 1: Register all 14 templates**

Instantiate every new class in `OrderExportTemplateRegistry::default()` after the existing templates. Registry keys remain unique and aliases map to the same instance.

- [ ] **Step 2: Centralize sleeve icon header candidates**

Add protected candidate methods and use them in common icon writing and sleeve-position detection.

```php
protected function leftSleeveIconHeaders()
{
    return ['左袖图标', '左袖符号', '左袖图标1', '左袖符号1', '左袖发泡符号'];
}

protected function rightSleeveIconHeaders()
{
    return ['右袖图标', '右袖符号', '右袖图标1', '右袖符号1', '右袖发泡符号'];
}
```

- [ ] **Step 3: Add registry and exact-header tests**

Assert every alias resolves to the intended key. For each new template, compare `array_slice($template->headers(), 0, -4)` to the exact hardcoded source header array and assert the final four headers are:

```php
['产品规格', 'sku', 'cleaned_sku', '产品链接']
```

- [ ] **Step 4: Verify common rules on representative new templates**

Use 发泡卫衣 for `左袖发泡符号`, 毛巾绣 for `右袖图标1`, and any gift template for `贺卡/包装`. Assert image paths, sleeve-position values, and ignored placeholder values behave like existing templates.

- [ ] **Step 5: Run focused template tests**

```powershell
php artisan test tests/Unit/OrderExportTemplates/ExpandedTemplateRegistryTest.php
php artisan test tests/Unit/OrderExportTemplates/TemplateHeadersTest.php
php artisan test tests/Unit/OrderExportTemplates/CommonOptionRulesTest.php
```

Expected: all files pass.

### Task 5: Grouping Integration And Final Verification

**Files:**
- Modify: `tests/Unit/DataProcessingServiceTest.php`

- [ ] **Step 1: Cover new template grouping**

Create representative candidate rows for `发泡卫衣`, `数码印T恤`, `亮片贴布绣`, and `双面卫衣-烫画`; assert four groups use their expected template keys. Retain the assertion that an unconfigured category enters no business group.

- [ ] **Step 2: Confirm PHPUnit database isolation**

Read `phpunit.xml` and require `DB_CONNECTION=sqlite` plus `DB_DATABASE=:memory:` before tests. Do not run migrations, seeders, or imports against `database/database.sqlite`.

- [ ] **Step 3: Run the complete targeted regression set**

```powershell
php artisan test tests/Unit/OrderExportTemplates
php artisan test tests/Unit/DataProcessingServiceTest.php
```

Expected: all targeted tests pass.

- [ ] **Step 4: Review scope and commit**

Run `git diff --check` and `git status --short`. Confirm private workbooks, `outputs/`, `scripts/__pycache__/`, and multi-website SKU scraping work are not staged.

```bash
git add app/Services/OrderExportTemplates app/Services/DataProcessingService.php tests/Unit/OrderExportTemplates tests/Unit/DataProcessingServiceTest.php
git commit -m "feat: add remaining clothing export templates"
```
