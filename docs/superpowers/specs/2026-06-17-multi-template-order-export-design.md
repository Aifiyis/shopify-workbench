# Multi-Template Order Export Design

## Goal

Refactor `DataProcessingService` so uploaded order files can generate one all-orders workbook plus separate specialized workbooks by `中文名称`, using `sku-cleaned.json` as the source of truth for product category resolution.

## Current State

`DataProcessingService` currently reads SKU category data from `storage/app/private/all-sku-to-product_type.json` and only routes rows with `中文名称 === '彩图刺绣'` into a specialized CTCX workbook.

`SkuCleaningService` already resolves a raw SKU through `storage/app/private/sku-cleaned.json`:

- Exact `original_sku` match first.
- If not found, clean the SKU using `sku-exclude-values.json`.
- Match the resulting `cleaned_sku`.
- Return the matched `中文名称` as `excel_category` and `type`.

The export flow should use this existing service instead of maintaining a second SKU lookup path inside `DataProcessingService`.

## Scope

The first template-class trial covers these specialized exports:

- `彩图刺绣`
- `领口破洞刺绣`
- Pet image template: `宠物轮廓`, `宠物彩图`, and `宠物轮廓彩图` if it appears in future data
- Person image template: `人物轮廓`, `人物彩图`, and `人物轮廓彩图` if it appears in future data
- Heat-transfer clothing template: `普通烫画卫衣` and `普通烫画衣服`

Rows whose resolved `中文名称` has no configured template stay only in the all-orders workbook.

## Architecture

Add a small template layer under `app/Services/OrderExportTemplates/`.

`DataProcessingService` remains the orchestration point for reading the uploaded workbook, embedding images, writing workbooks, and building the zip archive. It should stop owning category-specific field rules.

Each template class owns:

- The Chinese names it supports.
- The output file label.
- The headers for its workbook.
- The row mapping rules from source row data and parsed product specs to output columns.

Shared parsing and mapping helpers live in a reusable base class so later templates can reuse field extraction without copying logic.

## Data Flow

1. Read the source workbook once.
2. For each nonblank source row:
   - Read order ID, SKU, specs, picture, and quantity.
   - Resolve SKU through `SkuCleaningService`.
   - Write the all-orders workbook with appended `中文名称`, `工艺`, `处理人`, and `上品人` values from `sku-cleaned.json` where available.
   - If the resolved `中文名称` is registered to a template, append a normalized row payload to that template group.
3. After the all-orders workbook is written, generate one workbook per nonempty template group.
4. Zip the all-orders workbook and all generated specialized workbooks.

## Template Registry

Add `OrderExportTemplateRegistry` to map `中文名称` to a template instance.

The registry should be explicit. It should not automatically generate workbooks for every name in `sku-cleaned.json`, because the data file contains many categories and most do not yet have a confirmed template.

## Row Payload

Template classes receive a normalized associative array instead of reading Excel cells directly:

```php
[
    'filename_key' => $filenameKey,
    'source_row' => $row,
    'order_id' => $orderId,
    'sku' => $sku,
    'cleaned_sku' => $resolvedSku['cleaned_sku'],
    'chinese_name' => $resolvedSku['excel_category'],
    'product_specs' => $productSpecs,
    'product_image' => $productImage,
    'quantity' => $quantity,
    'style' => $style,
    'color' => $color,
    'size' => $size,
]
```

This keeps template rules testable without constructing a full Excel workbook.

## First Template Behavior

`彩图刺绣` should preserve current behavior for these SKU rules:

- `CS-QK0743-CX`
  - Build `胸部文本` from state options and year.
  - Translate text thread color through color lookup into `胸部文本颜色`.
  - Set `胸部位置` to `胸部中央`.
- `CS-QK2571-CX`
  - Set `全彩/轮廓` to `全彩`.
  - Copy thread color into `袖子绣线颜色`.
  - Map embroidery placement into `胸部位置`.
  - Copy photo into `胸部图片`.

The existing helper conflict around `formatCtcxEstYearLine()` should be resolved while migrating this rule. The helper should return only the normalized year part, and the caller should build `第二行：EST. {year}`.

`领口破洞刺绣`, pet image, person image, and heat-transfer clothing can start with shared base columns plus conservative image/text mapping. The initial implementation should not invent unsupported business fields. Empty fields are acceptable when the source specs do not contain a recognized option.

## File Naming

Specialized workbook names should use:

```text
order_output_{templateLabel}{filenameKey}.xlsx
```

Examples:

- `order_output_彩图刺绣0601 09-0602 09.xlsx`
- `order_output_领口破洞刺绣0601 09-0602 09.xlsx`
- `order_output_宠物轮廓彩图0601 09-0602 09.xlsx`
- `order_output_人物轮廓彩图0601 09-0602 09.xlsx`
- `order_output_普通烫画衣服0601 09-0602 09.xlsx`

## Testing Strategy

Use narrow PHPUnit tests only after confirming `phpunit.xml` keeps SQLite `:memory:`.

Unit tests should cover:

- `SkuCleaningService` remains the source for `cleaned_sku` and `中文名称`.
- The registry maps aliases to the correct templates.
- `彩图刺绣` template preserves the current two SKU-specific rules.
- Unknown `中文名称` is not routed to a specialized template.
- `DataProcessingService` groups rows by resolved category without using `all-sku-to-product_type.json` for routing.

Workbook-level tests can be limited to small synthetic workbooks with a few rows and no network image downloads.

## Non-Goals

- Do not generate specialized workbooks for every `中文名称` in `sku-cleaned.json`.
- Do not redesign image embedding.
- Do not change SKU cleaning rules.
- Do not run destructive database commands.
- Do not bulk-regenerate private SKU data files.
