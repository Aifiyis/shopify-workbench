# Multi-Template Order Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a template-based order export flow that resolves `中文名称` from `sku-cleaned.json`, generates specialized workbooks for configured categories, and leaves unconfigured categories only in the all-orders workbook.

**Architecture:** Keep `DataProcessingService` as the workbook orchestrator, but move category-specific mapping into template classes under `app/Services/OrderExportTemplates/`. Use `SkuCleaningService` for SKU resolution, and use an explicit registry so only configured Chinese names produce specialized workbooks.

**Tech Stack:** Laravel 8, PHP 7.3-compatible syntax, PHPExcel, PHPUnit through `php artisan test`.

---

## File Structure

- Create: `app/Services/OrderExportTemplates/OrderExportTemplate.php`
  - Interface for template metadata and row mapping.
- Create: `app/Services/OrderExportTemplates/AbstractOrderExportTemplate.php`
  - Shared helpers for base values, spec parsing, lookup translation, and position mapping.
- Create: `app/Services/OrderExportTemplates/CtcxTemplate.php`
  - Migrates the existing `applyCtcxSkuRules()` behavior.
- Create: `app/Services/OrderExportTemplates/NeckHoleEmbroideryTemplate.php`
  - First pass template for `领口破洞刺绣`.
- Create: `app/Services/OrderExportTemplates/PetOutlineColorTemplate.php`
  - Handles `宠物轮廓`, `宠物彩图`, and `宠物轮廓彩图`.
- Create: `app/Services/OrderExportTemplates/PersonOutlineColorTemplate.php`
  - Handles `人物轮廓`, `人物彩图`, and `人物轮廓彩图`.
- Create: `app/Services/OrderExportTemplates/HeatTransferClothingTemplate.php`
  - Handles `普通烫画卫衣` and `普通烫画衣服`.
- Create: `app/Services/OrderExportTemplates/OrderExportTemplateRegistry.php`
  - Explicit mapping from resolved `中文名称` to template instance.
- Modify: `app/Services/DataProcessingService.php`
  - Inject `SkuCleaningService`, use the registry, replace hard-coded CTCX row routing, and generate all nonempty specialized template workbooks.
- Modify: `tests/Unit/DataProcessingServiceTest.php`
  - Update legacy CTCX tests or move them to template tests.
- Create: `tests/Unit/OrderExportTemplateRegistryTest.php`
  - Tests category alias routing.
- Create: `tests/Unit/OrderExportTemplates/CtcxTemplateTest.php`
  - Tests current CTCX SKU-specific rules.
- Create: `tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php`
  - Tests conservative mappings for the other first-pass templates.

### Task 1: Add Template Interface And Base Class

**Files:**
- Create: `app/Services/OrderExportTemplates/OrderExportTemplate.php`
- Create: `app/Services/OrderExportTemplates/AbstractOrderExportTemplate.php`
- Test: `tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php`

- [ ] **Step 1: Write the failing test for shared base output shape**

Create `tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php` with:

```php
<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\HeatTransferClothingTemplate;
use Tests\TestCase;

class BasicTemplateRoutingTest extends TestCase
{
    public function test_heat_transfer_template_returns_base_row_values()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-1',
            'product_image' => 'https://example.test/image.png',
            'style' => 'Hoodie',
            'color' => 'White',
            'size' => 'XL',
            'quantity' => 2,
            'sku' => 'CS-QK1000',
            'cleaned_sku' => 'CS-QK1000',
            'product_specs' => "Color: White\nSize: XL\nMaterial: Cotton\nPhoto: https://example.test/design.png",
        ], []);

        $this->assertSame('0601', $row[0]);
        $this->assertSame('ORDER-1', $row[1]);
        $this->assertSame('https://example.test/image.png', $row[2]);
        $this->assertSame('Hoodie', $row[4]);
        $this->assertSame('White', $row[5]);
        $this->assertSame('XL', $row[6]);
        $this->assertSame(2, $row[7]);
        $this->assertSame('https://example.test/design.png', $row[22]);
        $this->assertSame('CS-QK1000', $row[30]);
        $this->assertSame('CS-QK1000', $row[31]);
        $this->assertSame("Color: White\nSize: XL\nMaterial: Cotton\nPhoto: https://example.test/design.png", $row[32]);
    }
}
```

- [ ] **Step 2: Run the narrow failing test**

Run:

```powershell
php artisan test tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php
```

Expected: fail because the template classes do not exist.

- [ ] **Step 3: Add the interface**

Create `app/Services/OrderExportTemplates/OrderExportTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

interface OrderExportTemplate
{
    public function key();

    public function label();

    public function supportedChineseNames();

    public function headers();

    public function mapRow(array $row, array $context);
}
```

- [ ] **Step 4: Add the base class and first simple template**

Create `app/Services/OrderExportTemplates/AbstractOrderExportTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

abstract class AbstractOrderExportTemplate implements OrderExportTemplate
{
    public function headers()
    {
        return [
            '导表日期',
            '订单号',
            '款图',
            '是否做货',
            '款式',
            '衣服颜色',
            '尺码',
            '数量',
            '左袖文本',
            '左袖图标',
            '左袖字体',
            '备注',
            '袖子位置',
            '右袖文本',
            '右袖图标',
            '备注',
            '袖子位置',
            '袖子绣线颜色',
            '胸部文字风格',
            '胸部文本',
            '全彩/轮廓',
            '胸部文本颜色',
            '胸部图片',
            'this字体',
            '第二行字体',
            '第三行字体',
            '胸部位置',
            '贺卡',
            '礼品袋',
            '设计稿',
            'sku',
            'cleaned_sku',
            '产品规格',
        ];
    }

    public function mapRow(array $row, array $context)
    {
        $values = $this->baseValues($row);
        return $this->applyRules($values, $row, $context);
    }

    protected function baseValues(array $row)
    {
        $values = [
            $row['filename_key'] ?? '',
            $row['order_id'] ?? '',
            $row['product_image'] ?? '',
            '',
            $row['style'] ?? '',
            $row['color'] ?? '',
            $row['size'] ?? '',
            $row['quantity'] ?? '',
        ];

        $values[30] = $row['sku'] ?? '';
        $values[31] = $row['cleaned_sku'] ?? '';
        $values[32] = $row['product_specs'] ?? '';

        return $values;
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        return $values;
    }

    protected function attributesAfter($specs, $skipCount)
    {
        if (empty($specs)) {
            return [];
        }

        $attributes = [];
        $lines = preg_split('/\r\n|\n|\r/', trim((string) $specs));

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }

            list($name, $value) = explode(':', $line, 2);
            $attributes[] = [
                'name' => trim($name),
                'value' => trim($value),
            ];
        }

        return array_slice($attributes, $skipCount);
    }

    protected function firstAttributeValue(array $attributes, array $needles)
    {
        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);
            $matches = true;

            foreach ($needles as $needle) {
                if (strpos($name, strtolower($needle)) === false) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $attribute['value'];
            }
        }

        return '';
    }

    protected function translateLookupValue($value, array $lookup)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (isset($lookup[$value])) {
            return $lookup[$value];
        }

        foreach ($lookup as $key => $translated) {
            if (strcasecmp($value, (string) $key) === 0) {
                return $translated;
            }
        }

        foreach ($lookup as $key => $translated) {
            if ($key !== '' && stripos($value, (string) $key) !== false) {
                return $translated;
            }
        }

        return $value;
    }

    protected function mapEmbroideryPosition($value)
    {
        $value = trim((string) $value);
        $lowerValue = strtolower($value);

        if (strpos($lowerValue, 'middle') !== false
            || strpos($lowerValue, 'center') !== false
            || strpos($lowerValue, 'centre') !== false) {
            return '胸部中央';
        }

        if (strpos($lowerValue, 'left') !== false) {
            return '左胸口';
        }

        if (strpos($lowerValue, 'right') !== false) {
            return '右胸口';
        }

        return $value;
    }
}
```

Create `app/Services/OrderExportTemplates/HeatTransferClothingTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

class HeatTransferClothingTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'heat_transfer_clothing';
    }

    public function label()
    {
        return '普通烫画衣服';
    }

    public function supportedChineseNames()
    {
        return ['普通烫画卫衣', '普通烫画衣服'];
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $values[22] = $photo;
        }

        return $values;
    }
}
```

- [ ] **Step 5: Run the test again**

Run:

```powershell
php artisan test tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php
```

Expected: pass.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/OrderExportTemplates/OrderExportTemplate.php app/Services/OrderExportTemplates/AbstractOrderExportTemplate.php app/Services/OrderExportTemplates/HeatTransferClothingTemplate.php tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php
git commit -m "feat: add order export template base"
```

### Task 2: Add CTCX Template And Preserve Existing Rules

**Files:**
- Create: `app/Services/OrderExportTemplates/CtcxTemplate.php`
- Test: `tests/Unit/OrderExportTemplates/CtcxTemplateTest.php`
- Modify: `tests/Unit/DataProcessingServiceTest.php`

- [ ] **Step 1: Write failing CTCX template tests**

Create `tests/Unit/OrderExportTemplates/CtcxTemplateTest.php`:

```php
<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\CtcxTemplate;
use Tests\TestCase;

class CtcxTemplateTest extends TestCase
{
    public function test_qk0743_builds_chest_text_color_and_position()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-1',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK0743-CX-001',
            'cleaned_sku' => 'ABC-CS-QK0743-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'State Options: California',
                'Year: EST.2026',
                'Text Thread Color: Red',
            ]),
        ], [
            'color_lookup' => ['Red' => '红色'],
        ]);

        $this->assertSame("第一行：California\n第二行：EST. 2026", $row[19]);
        $this->assertSame('红色', $row[21]);
        $this->assertSame('胸部中央', $row[26]);
    }

    public function test_qk2571_builds_full_color_photo_and_position()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-2',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK2571-CX-001',
            'cleaned_sku' => 'ABC-CS-QK2571-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'Thread Color: Gold',
                'Embroidery Position: Middle Chest',
                'Photo: https://example.test/photo.png',
            ]),
        ], [
            'color_lookup' => [],
        ]);

        $this->assertSame('Gold', $row[17]);
        $this->assertSame('全彩', $row[20]);
        $this->assertSame('https://example.test/photo.png', $row[22]);
        $this->assertSame('胸部中央', $row[26]);
    }
}
```

- [ ] **Step 2: Run the failing CTCX test**

Run:

```powershell
php artisan test tests/Unit/OrderExportTemplates/CtcxTemplateTest.php
```

Expected: fail because `CtcxTemplate` does not exist.

- [ ] **Step 3: Add `CtcxTemplate`**

Create `app/Services/OrderExportTemplates/CtcxTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

class CtcxTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'ctcx';
    }

    public function label()
    {
        return '彩图刺绣';
    }

    public function supportedChineseNames()
    {
        return ['彩图刺绣'];
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $sku = (string) ($row['sku'] ?? '');
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $colorLookup = $context['color_lookup'] ?? [];

        if (strpos($sku, 'CS-QK0743-CX') !== false) {
            return $this->applyQk0743Rules($values, $attributes, $colorLookup);
        }

        if (strpos($sku, 'CS-QK2571-CX') !== false) {
            return $this->applyQk2571Rules($values, $attributes);
        }

        return $values;
    }

    private function applyQk0743Rules(array $values, array $attributes, array $colorLookup)
    {
        $chestTextLines = [];

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);

            if (strpos($name, 'state options') !== false) {
                $chestTextLines[] = '第一行：' . $attribute['value'];
            }

            if (strpos($name, 'year') !== false) {
                $chestTextLines[] = '第二行：EST. ' . $this->formatEstYearPart($attribute['value']);
            }

            if (strpos($name, 'thread color') !== false) {
                $values[21] = $this->translateLookupValue($attribute['value'], $colorLookup);
            }
        }

        if (!empty($chestTextLines)) {
            $values[19] = implode("\n", $chestTextLines);
        }

        $values[26] = '胸部中央';

        return $values;
    }

    private function applyQk2571Rules(array $values, array $attributes)
    {
        $values[20] = '全彩';

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);

            if (strpos($name, 'thread color') !== false) {
                $values[17] = $attribute['value'];
            }

            if ((strpos($name, 'embroidery') !== false && strpos($name, 'position') !== false)
                || strpos($name, 'placement') !== false) {
                $values[26] = $this->mapEmbroideryPosition($attribute['value']);
            }

            if (strpos($name, 'photo') !== false) {
                $values[22] = $attribute['value'];
            }
        }

        return $values;
    }

    private function formatEstYearPart($yearValue)
    {
        $yearPart = trim((string) $yearValue);

        if (preg_match('/est/i', $yearPart)) {
            $yearPart = preg_replace('/^\s*est\.?\s*/i', '', $yearPart);
            $yearPart = trim($yearPart);
        }

        return $yearPart;
    }
}
```

- [ ] **Step 4: Run CTCX template tests**

Run:

```powershell
php artisan test tests/Unit/OrderExportTemplates/CtcxTemplateTest.php
```

Expected: pass.

- [ ] **Step 5: Remove or narrow legacy private-method tests**

In `tests/Unit/DataProcessingServiceTest.php`, remove `test_apply_ctcx_qk0743_rules`, `test_apply_ctcx_qk2571_rules`, `test_format_ctcx_est_year_line`, and `test_map_embroidery_position` after confirming equivalent tests exist in `CtcxTemplateTest`.

- [ ] **Step 6: Run related tests**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php tests/Unit/OrderExportTemplates/CtcxTemplateTest.php
```

Expected: pass.

- [ ] **Step 7: Commit**

```powershell
git add app/Services/OrderExportTemplates/CtcxTemplate.php tests/Unit/OrderExportTemplates/CtcxTemplateTest.php tests/Unit/DataProcessingServiceTest.php
git commit -m "feat: move ctcx rules into export template"
```

### Task 3: Add Registry And Remaining First-Pass Templates

**Files:**
- Create: `app/Services/OrderExportTemplates/OrderExportTemplateRegistry.php`
- Create: `app/Services/OrderExportTemplates/NeckHoleEmbroideryTemplate.php`
- Create: `app/Services/OrderExportTemplates/PetOutlineColorTemplate.php`
- Create: `app/Services/OrderExportTemplates/PersonOutlineColorTemplate.php`
- Test: `tests/Unit/OrderExportTemplateRegistryTest.php`
- Modify: `tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php`

- [ ] **Step 1: Write failing registry tests**

Create `tests/Unit/OrderExportTemplateRegistryTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\OrderExportTemplates\OrderExportTemplateRegistry;
use Tests\TestCase;

class OrderExportTemplateRegistryTest extends TestCase
{
    public function test_resolves_configured_chinese_names_to_templates()
    {
        $registry = OrderExportTemplateRegistry::default();

        $this->assertSame('ctcx', $registry->forChineseName('彩图刺绣')->key());
        $this->assertSame('neck_hole_embroidery', $registry->forChineseName('领口破洞刺绣')->key());
        $this->assertSame('pet_outline_color', $registry->forChineseName('宠物轮廓')->key());
        $this->assertSame('pet_outline_color', $registry->forChineseName('宠物彩图')->key());
        $this->assertSame('person_outline_color', $registry->forChineseName('人物轮廓')->key());
        $this->assertSame('person_outline_color', $registry->forChineseName('人物彩图')->key());
        $this->assertSame('heat_transfer_clothing', $registry->forChineseName('普通烫画卫衣')->key());
    }

    public function test_returns_null_for_unconfigured_chinese_name()
    {
        $registry = OrderExportTemplateRegistry::default();

        $this->assertNull($registry->forChineseName('毛毯'));
        $this->assertNull($registry->forChineseName(''));
    }
}
```

- [ ] **Step 2: Run the failing registry test**

Run:

```powershell
php artisan test tests/Unit/OrderExportTemplateRegistryTest.php
```

Expected: fail because the registry does not exist.

- [ ] **Step 3: Add the registry**

Create `app/Services/OrderExportTemplates/OrderExportTemplateRegistry.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

class OrderExportTemplateRegistry
{
    private $templatesByName = [];
    private $templatesByKey = [];

    public static function default()
    {
        return new self([
            new CtcxTemplate(),
            new NeckHoleEmbroideryTemplate(),
            new PetOutlineColorTemplate(),
            new PersonOutlineColorTemplate(),
            new HeatTransferClothingTemplate(),
        ]);
    }

    public function __construct(array $templates)
    {
        foreach ($templates as $template) {
            $this->templatesByKey[$template->key()] = $template;

            foreach ($template->supportedChineseNames() as $name) {
                $name = trim((string) $name);

                if ($name !== '') {
                    $this->templatesByName[$name] = $template;
                }
            }
        }
    }

    public function forChineseName($name)
    {
        $name = trim((string) $name);

        return $this->templatesByName[$name] ?? null;
    }

    public function templates()
    {
        return array_values($this->templatesByKey);
    }
}
```

- [ ] **Step 4: Add the remaining first-pass templates**

Create `app/Services/OrderExportTemplates/NeckHoleEmbroideryTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

class NeckHoleEmbroideryTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'neck_hole_embroidery';
    }

    public function label()
    {
        return '领口破洞刺绣';
    }

    public function supportedChineseNames()
    {
        return ['领口破洞刺绣'];
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $threadColor = $this->firstAttributeValue($attributes, ['thread color']);
        $text = $this->firstAttributeValue($attributes, ['text']);

        if ($text !== '') {
            $values[19] = $text;
        }

        if ($threadColor !== '') {
            $values[21] = $this->translateLookupValue($threadColor, $context['color_lookup'] ?? []);
        }

        return $values;
    }
}
```

Create `app/Services/OrderExportTemplates/PetOutlineColorTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

class PetOutlineColorTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'pet_outline_color';
    }

    public function label()
    {
        return '宠物轮廓彩图';
    }

    public function supportedChineseNames()
    {
        return ['宠物轮廓', '宠物彩图', '宠物轮廓彩图'];
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $values[22] = $photo;
        }

        if (($row['chinese_name'] ?? '') === '宠物轮廓') {
            $values[20] = '轮廓';
        }

        if (($row['chinese_name'] ?? '') === '宠物彩图') {
            $values[20] = '全彩';
        }

        return $values;
    }
}
```

Create `app/Services/OrderExportTemplates/PersonOutlineColorTemplate.php`:

```php
<?php

namespace App\Services\OrderExportTemplates;

class PersonOutlineColorTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'person_outline_color';
    }

    public function label()
    {
        return '人物轮廓彩图';
    }

    public function supportedChineseNames()
    {
        return ['人物轮廓', '人物彩图', '人物轮廓彩图'];
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $values[22] = $photo;
        }

        if (($row['chinese_name'] ?? '') === '人物轮廓') {
            $values[20] = '轮廓';
        }

        if (($row['chinese_name'] ?? '') === '人物彩图') {
            $values[20] = '全彩';
        }

        return $values;
    }
}
```

- [ ] **Step 5: Run registry tests**

Run:

```powershell
php artisan test tests/Unit/OrderExportTemplateRegistryTest.php
```

Expected: pass.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/OrderExportTemplates/OrderExportTemplateRegistry.php app/Services/OrderExportTemplates/NeckHoleEmbroideryTemplate.php app/Services/OrderExportTemplates/PetOutlineColorTemplate.php app/Services/OrderExportTemplates/PersonOutlineColorTemplate.php tests/Unit/OrderExportTemplateRegistryTest.php
git commit -m "feat: register first order export templates"
```

### Task 4: Wire SKU-Cleaned Resolution Into DataProcessingService

**Files:**
- Modify: `app/Services/DataProcessingService.php`
- Test: `tests/Unit/DataProcessingServiceTest.php`

- [ ] **Step 1: Add a failing test for template grouping**

Add this test to `tests/Unit/DataProcessingServiceTest.php`:

```php
public function test_groups_rows_only_for_configured_templates()
{
    $method = $this->getDataProcessingMethod('groupRowsByTemplate');

    $rows = [
        [
            'source_row' => 2,
            'chinese_name' => '彩图刺绣',
            'sku' => 'CS-QK2571-CX',
        ],
        [
            'source_row' => 3,
            'chinese_name' => '毛毯',
            'sku' => 'BLANKET-1',
        ],
        [
            'source_row' => 4,
            'chinese_name' => '人物彩图',
            'sku' => 'PERSON-1',
        ],
    ];

    $groups = $method->invoke($this->dataProcessingService, $rows);

    $this->assertArrayHasKey('ctcx', $groups);
    $this->assertArrayHasKey('person_outline_color', $groups);
    $this->assertArrayNotHasKey('毛毯', $groups);
    $this->assertCount(1, $groups['ctcx']['rows']);
    $this->assertCount(1, $groups['person_outline_color']['rows']);
}
```

- [ ] **Step 2: Run the failing test**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php --filter=groups_rows_only_for_configured_templates
```

Expected: fail because `groupRowsByTemplate` does not exist.

- [ ] **Step 3: Modify `DataProcessingService` constructor**

Update the top of `DataProcessingService` to include:

```php
use App\Services\OrderExportTemplates\OrderExportTemplateRegistry;
```

Update properties and constructor:

```php
private $lookupService;
private $skuCleaningService;
private $templateRegistry;

public function __construct(
    LookupService $lookupService,
    SkuCleaningService $skuCleaningService = null,
    OrderExportTemplateRegistry $templateRegistry = null
) {
    $this->lookupService = $lookupService;
    $this->skuCleaningService = $skuCleaningService ?: new SkuCleaningService();
    $this->templateRegistry = $templateRegistry ?: OrderExportTemplateRegistry::default();
}
```

- [ ] **Step 4: Add template grouping helper**

Add this private method to `DataProcessingService`:

```php
private function groupRowsByTemplate(array $rows)
{
    $groups = [];

    foreach ($rows as $row) {
        $template = $this->templateRegistry->forChineseName($row['chinese_name'] ?? '');

        if ($template === null) {
            continue;
        }

        $key = $template->key();

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'template' => $template,
                'rows' => [],
            ];
        }

        $groups[$key]['rows'][] = $row;
    }

    return $groups;
}
```

- [ ] **Step 5: Run the grouping test**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php --filter=groups_rows_only_for_configured_templates
```

Expected: pass.

- [ ] **Step 6: Replace `getSkuProductTypeLookup()` routing**

Inside `buildAllOrderFile()`, remove the dependency on `$skuLookup` for routing and use:

```php
$resolvedSku = $this->skuCleaningService->resolve($sku);
$chineseName = $resolvedSku['excel_category'] ?? '';
```

Build a row payload for every processed source row:

```php
$templateCandidateRows[] = [
    'filename_key' => $filenameKey,
    'source_row' => $row,
    'order_id' => $this->getCellValue($sourceSheet, $sourceColumns['order_id'], $row),
    'sku' => $sku,
    'cleaned_sku' => $resolvedSku['cleaned_sku'] ?? '',
    'chinese_name' => $chineseName,
    'product_specs' => $this->getCellValue($sourceSheet, $sourceColumns['specs'], $row),
    'product_image' => $pictureColumn === null ? '' : $this->getCellValue($sourceSheet, $pictureColumn, $row),
    'quantity' => $this->getCellValue($sourceSheet, $sourceColumns['quantity'], $row),
];
```

Return template groups instead of `ctcx_rows`:

```php
'template_groups' => $this->groupRowsByTemplate($templateCandidateRows),
```

- [ ] **Step 7: Commit**

```powershell
git add app/Services/DataProcessingService.php tests/Unit/DataProcessingServiceTest.php
git commit -m "feat: route order exports by cleaned sku category"
```

### Task 5: Generate One Workbook Per Template Group

**Files:**
- Modify: `app/Services/DataProcessingService.php`
- Test: `tests/Unit/DataProcessingServiceTest.php`

- [ ] **Step 1: Add a narrow filename test**

Add this test to `tests/Unit/DataProcessingServiceTest.php`:

```php
public function test_generates_template_output_filename()
{
    $method = $this->getDataProcessingMethod('generateTemplateOutputFilename');

    $this->assertSame(
        'order_output_人物轮廓彩图0601.xlsx',
        $method->invoke($this->dataProcessingService, '人物轮廓彩图', 'order_0601.xlsx')
    );
}
```

- [ ] **Step 2: Run the failing filename test**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php --filter=generates_template_output_filename
```

Expected: fail because `generateTemplateOutputFilename` does not exist.

- [ ] **Step 3: Add generic template filename method**

Add to `DataProcessingService`:

```php
private function generateTemplateOutputFilename($templateLabel, $sourceFilename)
{
    return $this->withXlsxExtension('order_output_' . $templateLabel . $this->extractFilenameKey($sourceFilename));
}
```

- [ ] **Step 4: Run the filename test**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php --filter=generates_template_output_filename
```

Expected: pass.

- [ ] **Step 5: Replace `processOrderFileCTCX()` with generic template workbook writer**

Add `processTemplateOrderFile()`:

```php
private function processTemplateOrderFile($sourceFilename, $template, array $rows, array $embeddedImagesByRow, array $context)
{
    $outputSpreadsheet = new PHPExcel();
    $outputSheet = $outputSpreadsheet->getActiveSheet();
    $headers = $template->headers();
    $imageTempFiles = [
        'paths' => [],
        'cache' => [],
    ];

    foreach ($headers as $index => $header) {
        $outputSheet->setCellValueByColumnAndRow($index, 1, $header);
    }

    $outputRow = 2;

    foreach ($rows as $row) {
        $values = $template->mapRow($row, $context);

        for ($column = 0; $column < count($headers); $column++) {
            if ($column === 2) {
                $this->setCellValueOrImage(
                    $outputSheet,
                    $column,
                    $outputRow,
                    $values[$column] ?? '',
                    $imageTempFiles,
                    $embeddedImagesByRow[$row['source_row']] ?? null
                );
                continue;
            }

            $outputSheet->setCellValueByColumnAndRow($column, $outputRow, $values[$column] ?? '');

            if ($column === 19 && !empty($values[$column])) {
                $outputSheet->getStyleByColumnAndRow($column, $outputRow)
                    ->getAlignment()
                    ->setWrapText(true);
            }
        }

        $outputRow++;
    }

    $this->adjustColumnWidths($outputSheet, count($headers));
    $outputSheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex(2))->setWidth(18);

    $outputFilename = $this->generateTemplateOutputFilename($template->label(), $sourceFilename);
    $outputPath = $this->getIntermediateFilePath($outputFilename);
    $this->ensureDirectory(dirname($outputPath));

    $writer = new PHPExcel_Writer_Excel2007($outputSpreadsheet);
    $writer->save($outputPath);
    $this->cleanupTempFiles($imageTempFiles);

    return [
        'filename' => $outputFilename,
        'path' => $outputPath,
        'rows_processed' => $outputRow - 2,
    ];
}
```

- [ ] **Step 6: Update `processOrderFileAll()` to append each template file**

Replace the CTCX-specific block with:

```php
$templateGroups = $allResult['template_groups'];

foreach ($templateGroups as $group) {
    $templateFile = $this->processTemplateOrderFile(
        $sourceFilename,
        $group['template'],
        $group['rows'],
        $embeddedImages['by_row'],
        [
            'color_lookup' => $this->lookupService->getColorLookup(),
        ]
    );

    $outputFiles[] = [
        'filename' => $templateFile['filename'],
        'path' => $templateFile['path'],
    ];
}
```

Set the return count to:

```php
'template_rows_processed' => array_sum(array_map(function ($group) {
    return count($group['rows']);
}, $templateGroups)),
```

Keep `ctcx_rows_processed` temporarily if the controller or view still reads it:

```php
'ctcx_rows_processed' => isset($templateGroups['ctcx']) ? count($templateGroups['ctcx']['rows']) : 0,
```

- [ ] **Step 7: Run related unit tests**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php tests/Unit/OrderExportTemplateRegistryTest.php tests/Unit/OrderExportTemplates/CtcxTemplateTest.php tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php
```

Expected: pass.

- [ ] **Step 8: Commit**

```powershell
git add app/Services/DataProcessingService.php tests/Unit/DataProcessingServiceTest.php
git commit -m "feat: generate specialized order export workbooks"
```

### Task 6: Verify End-To-End With A Synthetic Workbook

**Files:**
- Modify: `tests/Unit/DataProcessingServiceTest.php`

- [ ] **Step 1: Add synthetic workbook integration test**

Add a test that creates a tiny workbook with headers `Order ID`, `SKU`, `Product Specs`, `Picture`, and `Quantity`, writes rows for `彩图刺绣`, `人物彩图`, and `毛毯`, then processes the file with a temporary `SkuCleaningService` pointing to a test `sku-cleaned.json`.

Use this SKU data:

```php
[
    [
        'original_sku' => 'RAW-CTCX-1',
        'cleaned_sku' => 'CS-QK2571-CX',
        '中文名称' => '彩图刺绣',
        '工艺' => '刺绣',
        '处理人' => 'A',
        '上品人' => 'B',
    ],
    [
        'original_sku' => 'RAW-PERSON-1',
        'cleaned_sku' => 'PERSON-1',
        '中文名称' => '人物彩图',
        '工艺' => '彩图',
        '处理人' => 'A',
        '上品人' => 'B',
    ],
    [
        'original_sku' => 'RAW-BLANKET-1',
        'cleaned_sku' => 'BLANKET-1',
        '中文名称' => '毛毯',
        '工艺' => '',
        '处理人' => '',
        '上品人' => '',
    ],
]
```

Assert that the returned file list contains:

```php
$this->assertContains('order_output_all0601.xlsx', $result['files']);
$this->assertContains('order_output_彩图刺绣0601.xlsx', $result['files']);
$this->assertContains('order_output_人物轮廓彩图0601.xlsx', $result['files']);
$this->assertNotContains('order_output_毛毯0601.xlsx', $result['files']);
```

- [ ] **Step 2: Run the synthetic workbook test**

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php --filter=synthetic
```

Expected: pass.

- [ ] **Step 3: Run the focused suite**

Before running, confirm `phpunit.xml` contains:

```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
```

Run:

```powershell
php artisan test tests/Unit/DataProcessingServiceTest.php tests/Unit/OrderExportTemplateRegistryTest.php tests/Unit/OrderExportTemplates/CtcxTemplateTest.php tests/Unit/OrderExportTemplates/BasicTemplateRoutingTest.php tests/Unit/SkuCleaningServiceTest.php
```

Expected: pass.

- [ ] **Step 4: Commit**

```powershell
git add tests/Unit/DataProcessingServiceTest.php
git commit -m "test: cover multi-template order export flow"
```

## Self-Review Notes

- Spec coverage: the plan switches routing to `SkuCleaningService`, adds explicit configured templates, keeps unknown names out of specialized exports, preserves CTCX behavior, and includes the first five template families.
- Placeholder scan: the plan avoids placeholder markers and includes concrete code for each new class and test.
- Type consistency: template method names are consistent across interface, base class, registry, and `DataProcessingService`.
