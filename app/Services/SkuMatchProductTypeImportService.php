<?php

namespace App\Services;

use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use Illuminate\Support\Facades\DB;
use PHPExcel_IOFactory;

class SkuMatchProductTypeImportService
{
    const ALL_SHEET_START_ROW = 3502;
    const UPSERT_CHUNK_SIZE = 100;

    private $skuCleaningService;
    private $skuCleanedPath;
    private $workbookPath;

    public function __construct(
        SkuCleaningService $skuCleaningService = null,
        $skuCleanedPath = null,
        $workbookPath = null
    ) {
        $this->skuCleaningService = $skuCleaningService ?: new SkuCleaningService();
        $this->skuCleanedPath = $skuCleanedPath ?: storage_path('app/private/sku-cleaned.json');
        $this->workbookPath = $workbookPath ?: storage_path('app/private/sku-to-product_type.xlsx');
    }

    public function import()
    {
        $skuRows = $this->readJsonSkuRows();
        $workbookRows = $this->readWorkbookRows();

        foreach ($workbookRows['sku_rows'] as $row) {
            $this->mergeSkuRow($skuRows, $row);
        }

        $processRows = $workbookRows['process_rows'];
        $categoryNames = [];

        foreach ($skuRows as $row) {
            $categoryNames[$row['chinese_name']] = true;
        }

        $matchedSkuCount = 0;

        foreach ($skuRows as $row) {
            if (isset($processRows[$row['chinese_name']])) {
                $matchedSkuCount++;
            }
        }

        $result = DB::transaction(function () use ($skuRows, $processRows, $categoryNames, $matchedSkuCount) {
            foreach ($processRows as $row) {
                $craftNode = $this->resolveCraftPath($row['craft']);
                $configuration = ProductProcessingCraft::firstOrNew([
                    'chinese_name' => $row['chinese_name'],
                ]);
                $configuration->fill([
                    'craft_id' => $craftNode ? $craftNode->id : null,
                    'order_processor' => $row['order_processor'],
                    'artwork_processor' => $row['artwork_processor'],
                    'procurement_processor' => $row['procurement_processor'],
                ]);
                $configuration->save();
            }

            foreach (array_keys($categoryNames) as $chineseName) {
                ProductProcessingCraft::firstOrCreate([
                    'chinese_name' => $chineseName,
                ]);
            }

            $timestamp = now()->toDateTimeString();
            $databaseRows = [];

            foreach ($skuRows as $row) {
                $databaseRows[] = [
                    'original_sku' => $row['original_sku'],
                    'cleaned_sku' => $row['cleaned_sku'],
                    'chinese_name' => $row['chinese_name'],
                    'product_lister' => $row['product_lister'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            foreach (array_chunk($databaseRows, self::UPSERT_CHUNK_SIZE) as $chunk) {
                DB::table('sku_match_product_type')->upsert(
                    $chunk,
                    ['original_sku'],
                    ['cleaned_sku', 'chinese_name', 'product_lister', 'updated_at']
                );
            }

            return [
                'sku_count' => count($skuRows),
                'processing_config_count' => ProductProcessingCraft::count(),
                'craft_node_count' => ProcessingCraftNode::count(),
                'matched_sku_count' => $matchedSkuCount,
                'unmatched_sku_count' => count($skuRows) - $matchedSkuCount,
            ];
        });

        return $result;
    }

    private function readJsonSkuRows()
    {
        $rows = $this->readJsonArray($this->skuCleanedPath);
        $result = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Invalid sku-cleaned.json row: ' . ($index + 1));
            }

            $candidate = [
                'original_sku' => $this->requiredString($row['original_sku'] ?? null, 'sku-cleaned original_sku', $index + 1),
                'cleaned_sku' => $this->requiredString($row['cleaned_sku'] ?? null, 'sku-cleaned cleaned_sku', $index + 1),
                'chinese_name' => $this->requiredString($row['中文名称'] ?? null, 'sku-cleaned 中文名称', $index + 1),
                'product_lister' => $this->nullableString($row['上品人'] ?? null),
                'source' => 'json',
            ];

            $this->mergeSkuRow($result, $candidate);
        }

        return $result;
    }

    private function readWorkbookRows()
    {
        if (!file_exists($this->workbookPath)) {
            throw new \RuntimeException('Workbook not found: ' . $this->workbookPath);
        }

        $reader = PHPExcel_IOFactory::createReaderForFile($this->workbookPath);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($this->workbookPath);
        $allSheet = $workbook->getSheetByName('all');
        $processSheet = $workbook->getSheetByName('新处理工艺');

        if ($allSheet === null || $processSheet === null) {
            throw new \RuntimeException('Workbook must contain all and 新处理工艺 sheets.');
        }

        $this->assertHeaders($allSheet, ['sku', '中文名称', '上品人']);
        $this->assertHeaders($processSheet, ['中文名称', '工艺', '订单处理人', '图画处理人', '采购处理人']);

        $skuRows = [];

        for ($rowNumber = self::ALL_SHEET_START_ROW; $rowNumber <= $allSheet->getHighestDataRow(); $rowNumber++) {
            $originalSku = $this->cellString($allSheet, 0, $rowNumber);
            $chineseName = $this->cellString($allSheet, 1, $rowNumber);
            $productLister = $this->cellString($allSheet, 2, $rowNumber);

            if ($originalSku === '' && $chineseName === '' && $productLister === '') {
                continue;
            }

            $candidate = [
                'original_sku' => $this->requiredString($originalSku, 'all sku', $rowNumber),
                'cleaned_sku' => $this->skuCleaningService->cleanSkuUsingValuesAndPatterns($originalSku),
                'chinese_name' => $this->requiredString($chineseName, 'all 中文名称', $rowNumber),
                'product_lister' => $this->nullableString($productLister),
                'source' => 'excel',
            ];

            $this->mergeSkuRow($skuRows, $candidate);
        }

        $processRows = [];

        for ($rowNumber = 2; $rowNumber <= $processSheet->getHighestDataRow(); $rowNumber++) {
            $values = [];

            for ($column = 0; $column < 5; $column++) {
                $values[] = $this->cellString($processSheet, $column, $rowNumber);
            }

            if (implode('', $values) === '') {
                continue;
            }

            $candidate = [
                'chinese_name' => $this->requiredString($values[0], '新处理工艺 中文名称', $rowNumber),
                'craft' => $this->nullableString($values[1]),
                'order_processor' => $this->nullableString($values[2]),
                'artwork_processor' => $this->nullableString($values[3]),
                'procurement_processor' => $this->nullableString($values[4]),
            ];

            $name = $candidate['chinese_name'];

            if (isset($processRows[$name]) && $processRows[$name] !== $candidate) {
                throw new \RuntimeException('Conflicting 新处理工艺 rows for 中文名称: ' . $name);
            }

            $processRows[$name] = $candidate;
        }

        $workbook->disconnectWorksheets();

        return [
            'sku_rows' => $skuRows,
            'process_rows' => $processRows,
        ];
    }

    private function mergeSkuRow(array &$rows, array $candidate)
    {
        $key = $candidate['original_sku'];

        if (!isset($rows[$key])) {
            $rows[$key] = $candidate;
            return;
        }

        $existing = $rows[$key];

        if ($existing['chinese_name'] !== $candidate['chinese_name']
            || $existing['product_lister'] !== $candidate['product_lister']) {
            throw new \RuntimeException('Conflicting SKU rows for original_sku: ' . $key);
        }

        if ($existing['source'] === $candidate['source']
            && $existing['cleaned_sku'] !== $candidate['cleaned_sku']) {
            throw new \RuntimeException('Conflicting cleaned_sku rows for original_sku: ' . $key);
        }

        if ($candidate['source'] === 'json') {
            $rows[$key] = $candidate;
        }
    }

    private function resolveCraftPath($craft)
    {
        $parts = array_values(array_filter(array_map('trim', explode('-', (string) $craft)), function ($part) {
            return $part !== '';
        }));

        if (empty($parts)) {
            return null;
        }

        $parentId = null;
        $pathParts = [];
        $node = null;

        foreach ($parts as $part) {
            $pathParts[] = $part;
            $path = implode('-', $pathParts);
            $node = ProcessingCraftNode::firstOrCreate([
                'path' => $path,
            ], [
                'parent_id' => $parentId,
                'name' => $part,
            ]);
            $parentId = $node->id;
        }

        return $node;
    }

    private function assertHeaders($sheet, array $expected)
    {
        foreach ($expected as $column => $header) {
            if ($this->cellString($sheet, $column, 1) !== $header) {
                throw new \RuntimeException('Unexpected header in sheet ' . $sheet->getTitle() . ': ' . $header);
            }
        }
    }

    private function cellString($sheet, $column, $row)
    {
        return trim((string) $sheet->getCellByColumnAndRow($column, $row)->getValue());
    }

    private function requiredString($value, $field, $row)
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new \RuntimeException("Missing {$field} at row {$row}.");
        }

        return $value;
    }

    private function nullableString($value)
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function readJsonArray($path)
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('JSON file not found: ' . $path);
        }

        $data = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Invalid JSON ' . $path . ': ' . json_last_error_msg());
        }

        return $data;
    }
}
