<?php

namespace App\Services;

use App\Services\OrderExportTemplates\OrderExportTemplateRegistry;
use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_Cell_DataType;
use PHPExcel_IOFactory;
use PHPExcel_Writer_Excel2007;
use ZipArchive;

class DataProcessingService
{
    private $lookupService;
    private $skuCleaningService;
    private $templateRegistry;
    private $skuOptionImageResolver;
    private $skuPlacementResolver;
    private $logoLookupResolver;
    private $colorTranslationResolver;

    public function __construct(
        LookupService $lookupService,
        SkuCleaningService $skuCleaningService = null,
        OrderExportTemplateRegistry $templateRegistry = null,
        SkuOptionImageResolver $skuOptionImageResolver = null,
        SkuPlacementResolver $skuPlacementResolver = null,
        LogoLookupResolver $logoLookupResolver = null,
        ColorTranslationResolver $colorTranslationResolver = null
    )
    {
        $this->lookupService = $lookupService;
        $this->skuCleaningService = $skuCleaningService ?: new SkuCleaningService();
        $this->templateRegistry = $templateRegistry ?: OrderExportTemplateRegistry::default();
        $this->skuOptionImageResolver = $skuOptionImageResolver ?: new SkuOptionImageResolver();
        $this->skuPlacementResolver = $skuPlacementResolver ?: new SkuPlacementResolver();
        $this->logoLookupResolver = $logoLookupResolver ?: new LogoLookupResolver();
        $this->colorTranslationResolver = $colorTranslationResolver ?: ColorTranslationResolver::fromConfig();
    }

    public function processOrderFileAll($sourceFilePath, $sourceFilename = null)
    {
        if (!file_exists($sourceFilePath)) {
            throw new \Exception("Source file not found: {$sourceFilePath}");
        }

        $embeddedImages = [
            'by_row' => [],
            'paths' => [],
        ];

        try {
            $embeddedImages = $this->getEmbeddedImagesByRow($sourceFilePath);
            $sourceExcel = $this->loadSourceExcel($sourceFilePath);
            $sourceSheet = $sourceExcel->getActiveSheet();
            $sourceFilename = $sourceFilename ?: basename($sourceFilePath);
            $filenameKey = $this->extractFilenameKey($sourceFilename);
            $styleLookup = $this->lookupService->getStyleLookup();
            $colorLookup = $this->lookupService->getColorLookup();
            $sourceColumns = $this->getSourceColumnIndices($sourceSheet, 1);

            if ($sourceColumns['sku'] === null) {
                throw new \Exception('SKU column not found in source file.');
            }

            $allResult = $this->buildAllOrderFile(
                $sourceSheet,
                $sourceColumns,
                $styleLookup,
                $colorLookup,
                $filenameKey,
                $sourceFilename,
                $embeddedImages['by_row']
            );

            $outputFiles = [$allResult['file']];
            $templateGroups = $allResult['template_groups'];

            foreach ($templateGroups as $group) {
                $templateFile = $this->processTemplateOrderFile(
                    $sourceFilename,
                    $group['template'],
                    $group['rows'],
                    $embeddedImages['by_row'],
                    [
                        'color_lookup' => $colorLookup,
                        'sku_option_image_resolver' => $this->skuOptionImageResolver,
                        'sku_placement_resolver' => $this->skuPlacementResolver,
                        'logo_lookup_resolver' => $this->logoLookupResolver,
                        'color_translation_resolver' => $this->colorTranslationResolver,
                    ]
                );

                $outputFiles[] = [
                    'filename' => $templateFile['filename'],
                    'path' => $templateFile['path'],
                ];
            }

            $archive = $this->createDownloadArchive($outputFiles, $filenameKey);
            $this->cleanupTempPaths($embeddedImages['paths']);

            return [
                'success' => true,
                'output_filename' => $archive['filename'],
                'output_path' => $archive['path'],
                'rows_processed' => $allResult['rows_processed'],
                'template_rows_processed' => array_sum(array_map(function ($group) {
                    return count($group['rows']);
                }, $templateGroups)),
                'ctcx_rows_processed' => isset($templateGroups['ctcx']) ? count($templateGroups['ctcx']['rows']) : 0,
                'files' => array_column($outputFiles, 'filename'),
            ];
        } catch (\Exception $e) {
            $this->cleanupTempPaths($embeddedImages['paths']);
            \Log::error('Data processing failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

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
            $this->writeTextCell($outputSheet, $index, 1, $header);
        }

        $outputRow = 2;

        foreach ($rows as $row) {
            $values = $template->mapRow($row, $context);

            for ($column = 0; $column < count($headers); $column++) {
                $this->setTemplateCellValue(
                    $outputSheet,
                    $headers,
                    $column,
                    $outputRow,
                    $values[$column] ?? '',
                    $imageTempFiles,
                    $column === 2 ? ($embeddedImagesByRow[$row['source_row']] ?? null) : null
                );
            }

            $outputRow++;
        }

        $this->adjustColumnWidths($outputSheet, count($headers));
        $this->adjustImageColumns($outputSheet, $headers);
        $this->adjustReviewColumns($outputSheet, $headers);

        $outputFilename = $this->generateTemplateOutputFilename($template->label(), $sourceFilename);
        $outputPath = $this->getIntermediateFilePath($outputFilename);
        $this->ensureDirectory(dirname($outputPath));

        $writer = new PHPExcel_Writer_Excel2007($outputSpreadsheet);
        $this->saveExcelWriter($writer, $outputPath);
        $this->cleanupTempFiles($imageTempFiles);

        return [
            'filename' => $outputFilename,
            'path' => $outputPath,
            'rows_processed' => $outputRow - 2,
        ];
    }

    /**
     * 导出彩图刺绣处理表
     */
    public function processOrderFileCTCX($sourceFilePath, array $includedSourceRows = null, $sourceFilename = null, array $embeddedImagesByRow = null)
    {
        if (!file_exists($sourceFilePath)) {
            throw new \Exception("Source file not found: {$sourceFilePath}");
        }

        $embeddedImages = [
            'by_row' => $embeddedImagesByRow ?: [],
            'paths' => [],
        ];

        try {
            if ($embeddedImagesByRow === null) {
                $embeddedImages = $this->getEmbeddedImagesByRow($sourceFilePath);
            }

            $sourceExcel = $this->loadSourceExcel($sourceFilePath);
            $sourceSheet = $sourceExcel->getActiveSheet();
            $sourceFilename = $sourceFilename ?: basename($sourceFilePath);
            $filenameKey = $this->extractFilenameKey($sourceFilename);
            $styleLookup = $this->lookupService->getStyleLookup();
            $colorLookup = $this->lookupService->getColorLookup();
            $outputSpreadsheet = new PHPExcel();
            $outputSheet = $outputSpreadsheet->getActiveSheet();
            $template = $this->templateRegistry->forChineseName('彩图刺绣');
            $headers = $template->headers();
            $imageTempFiles = [
                'paths' => [],
                'cache' => [],
            ];

            foreach ($headers as $index => $header) {
                $this->writeTextCell($outputSheet, $index, 1, $header);
            }

            $highestRow = $sourceSheet->getHighestRow();
            $outputRow = 2;
            $headerRow = 1;
            $sourceColumns = $this->getSourceColumnIndices($sourceSheet, $headerRow);
            $includedSourceRows = $includedSourceRows === null ? null : array_flip($includedSourceRows);

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                if ($includedSourceRows !== null && !isset($includedSourceRows[$row])) {
                    continue;
                }

                $orderId = $this->getCellValue($sourceSheet, $sourceColumns['order_id'], $row);
                $sku = $this->getCellValue($sourceSheet, $sourceColumns['sku'], $row);
                $productSpecs = $this->getCellValue($sourceSheet, $sourceColumns['specs'], $row);
                $productImage = $this->getCellValue($sourceSheet, $sourceColumns['picture'], $row);
                $quantity = $this->getCellValue($sourceSheet, $sourceColumns['quantity'], $row);
                $productLink = $this->getCellValue($sourceSheet, $sourceColumns['product_link'], $row);

                if ($this->isBlank($orderId)) {
                    continue;
                }

                // style和color从private\lookups\color_lookup.json和style_lookup.json中匹配
                $style = $this->lookupService->matchStyle($sku, $styleLookup);
                $color = $this->lookupService->matchColor($productSpecs, $colorLookup);
                $size = $this->lookupService->extractSize($productSpecs);
                $resolvedSku = $this->skuCleaningService->resolve($sku);

                $values = $template->mapRow([
                    'filename_key' => $filenameKey,
                    'source_row' => $row,
                    'order_id' => $orderId,
                    'sku' => $sku,
                    'cleaned_sku' => $resolvedSku['cleaned_sku'] ?? '',
                    'chinese_name' => '彩图刺绣',
                    'product_specs' => $productSpecs,
                    'product_link' => $productLink ?? '',
                    'product_image' => $productImage ?? '',
                    'quantity' => $quantity ?? '',
                    'style' => $style ?? '',
                    'color' => $color ?? '',
                    'size' => $size ?? '',
                ], [
                    'color_lookup' => $colorLookup,
                    'sku_option_image_resolver' => $this->skuOptionImageResolver,
                    'sku_placement_resolver' => $this->skuPlacementResolver,
                    'logo_lookup_resolver' => $this->logoLookupResolver,
                    'color_translation_resolver' => $this->colorTranslationResolver,
                ]);

                for ($column = 0; $column < count($headers); $column++) {
                    $this->setTemplateCellValue(
                        $outputSheet,
                        $headers,
                        $column,
                        $outputRow,
                        $values[$column] ?? '',
                        $imageTempFiles,
                        $column === 2 ? ($embeddedImages['by_row'][$row] ?? null) : null
                    );
                }

                $outputRow++;
            }

            $this->adjustColumnWidths($outputSheet, count($headers));
            $this->adjustImageColumns($outputSheet, $headers);
            $this->adjustReviewColumns($outputSheet, $headers);

            $outputFilename = $this->generateCtcxOutputFilename($sourceFilename);
            $outputPath = $this->getIntermediateFilePath($outputFilename);
            $this->ensureDirectory(dirname($outputPath));

            $writer = new PHPExcel_Writer_Excel2007($outputSpreadsheet);
            $this->saveExcelWriter($writer, $outputPath);
            $this->cleanupTempFiles($imageTempFiles);
            $this->cleanupTempPaths($embeddedImages['paths']);

            return [
                'success' => true,
                'output_filename' => $outputFilename,
                'output_path' => $outputPath,
                'rows_processed' => $outputRow - 2,
            ];
        } catch (\Exception $e) {
            $this->cleanupTempPaths($embeddedImages['paths']);
            \Log::error('Data processing failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // 未使用（原来的测试方法后改为彩图刺绣入口）
    public function processOrderFile($sourceFilePath)
    {
        return $this->processOrderFileCTCX($sourceFilePath);
    }

    private function loadSourceExcel($sourceFilePath)
    {
        $reader = PHPExcel_IOFactory::createReaderForFile($sourceFilePath);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($sourceFilePath);
    }

    private function buildAllOrderFile($sourceSheet, array $sourceColumns, array $styleLookup, array $colorLookup, $filenameKey, $sourceFilename, array $embeddedImagesByRow = [])
    {
        $outputSpreadsheet = new PHPExcel();
        $outputSheet = $outputSpreadsheet->getActiveSheet();
        $highestRow = $sourceSheet->getHighestRow();
        $sourceColumnCount = $this->getSourceDataColumnCount($sourceSheet, 1);
        $appendStartColumn = $sourceColumnCount + 1;
        $ctcxRows = [];
        $templateCandidateRows = [];
        $rowsProcessed = 0;
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];
        $skuColumn = $sourceColumns['sku'];
        $pictureColumn = $sourceColumns['picture'];

        $this->writeTextCell($outputSheet, 0, 1, '导表日期');

        for ($column = 0; $column < $sourceColumnCount; $column++) {
            $this->writeTextCell(
                $outputSheet,
                $column + 1,
                1,
                $this->getCellValue($sourceSheet, $column, 1)
            );
        }

        foreach (['中文名称', '工艺', '处理人', '上品人'] as $offset => $header) {
            $this->writeTextCell($outputSheet, $appendStartColumn + $offset, 1, $header);
        }

        $outputRow = 2;

        for ($row = 2; $row <= $highestRow; $row++) {
            if ($this->isSourceRowBlank($sourceSheet, $row, $sourceColumnCount)) {
                continue;
            }

            $sku = $this->normalizeLookupKey($this->getCellValue($sourceSheet, $skuColumn, $row));
            $resolvedSku = $this->skuCleaningService->resolve($sku);
            $chineseName = $resolvedSku['excel_category'] ?? '';
            $productSpecs = $this->getCellValue($sourceSheet, $sourceColumns['specs'], $row);
            $productImage = $this->getCellValue($sourceSheet, $pictureColumn, $row);
            $quantity = $this->getCellValue($sourceSheet, $sourceColumns['quantity'], $row);
            $productLink = $this->getCellValue($sourceSheet, $sourceColumns['product_link'], $row);

            $this->writeTextCell($outputSheet, 0, $outputRow, $filenameKey);

            for ($column = 0; $column < $sourceColumnCount; $column++) {
                $value = $this->getCellValue($sourceSheet, $column, $row);

                if ($pictureColumn !== null && $column === $pictureColumn) {
                    $this->setCellValueOrImage($outputSheet, $column + 1, $outputRow, $value, $imageTempFiles, $embeddedImagesByRow[$row] ?? null);
                    continue;
                }

                $this->writeTextCell($outputSheet, $column + 1, $outputRow, $value);
            }

            $this->writeTextCell($outputSheet, $appendStartColumn, $outputRow, $chineseName);
            $this->writeTextCell($outputSheet, $appendStartColumn + 1, $outputRow, $resolvedSku['工艺'] ?? '');
            $this->writeTextCell($outputSheet, $appendStartColumn + 2, $outputRow, $resolvedSku['处理人'] ?? '');
            $this->writeTextCell($outputSheet, $appendStartColumn + 3, $outputRow, $resolvedSku['上品人'] ?? '');

            $templateCandidateRows[] = [
                'filename_key' => $filenameKey,
                'source_row' => $row,
                'order_id' => $this->getCellValue($sourceSheet, $sourceColumns['order_id'], $row),
                'sku' => $sku,
                'cleaned_sku' => $resolvedSku['cleaned_sku'] ?? '',
                'chinese_name' => $chineseName,
                'product_specs' => $productSpecs,
                'product_link' => $productLink ?? '',
                'product_image' => $productImage,
                'quantity' => $quantity,
                'style' => $this->lookupService->matchStyle($sku, $styleLookup) ?? '',
                'color' => $this->lookupService->matchColor($productSpecs, $colorLookup) ?? '',
                'size' => $this->lookupService->extractSize($productSpecs) ?? '',
            ];

            if ($chineseName === '彩图刺绣') {
                $ctcxRows[] = $row;
            }

            $outputRow++;
            $rowsProcessed++;
        }

        $this->adjustColumnWidths($outputSheet, $sourceColumnCount + 5);

        if ($pictureColumn !== null) {
            $outputSheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($pictureColumn + 1))->setWidth(18);
        }

        $outputFilename = $this->generateAllOutputFilename($sourceFilename);
        $outputPath = $this->getIntermediateFilePath($outputFilename);
        $this->ensureDirectory(dirname($outputPath));

        $writer = new PHPExcel_Writer_Excel2007($outputSpreadsheet);
        $this->saveExcelWriter($writer, $outputPath);
        $this->cleanupTempFiles($imageTempFiles);

        return [
            'file' => [
                'filename' => $outputFilename,
                'path' => $outputPath,
            ],
            'rows_processed' => $rowsProcessed,
            'ctcx_rows' => $ctcxRows,
            'template_groups' => $this->groupRowsByTemplate($templateCandidateRows),
        ];
    }

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

    private function getSkuProductTypeLookup()
    {
        $path = storage_path('app/private/all-sku-to-product_type.json');

        if (!file_exists($path)) {
            throw new \Exception("SKU lookup JSON not found: {$path}");
        }

        $records = json_decode(file_get_contents($path), true);

        if (!is_array($records)) {
            throw new \Exception('Invalid SKU lookup JSON: ' . json_last_error_msg());
        }

        $lookup = [];

        foreach ($records as $record) {
            $sku = $this->normalizeLookupKey($record['sku'] ?? '');

            if ($sku === '') {
                continue;
            }

            $lookup[$sku] = [
                '中文名称' => $record['中文名称'] ?? '',
                '工艺' => $record['工艺'] ?? '',
                '处理人' => $record['处理人'] ?? '',
                '上品人' => $record['上品人'] ?? '',
            ];
        }

        return $lookup;
    }

    private function getSourceColumnIndices($sheet, $headerRow)
    {
        $indices = [
            'order_id' => null,
            'sku' => null,
            'specs' => null,
            'picture' => null,
            'quantity' => null,
            'product_link' => null,
        ];

        $highestColumn = $sheet->getHighestColumn();
        $columnCount = PHPExcel_Cell::columnIndexFromString($highestColumn);

        for ($column = 0; $column < $columnCount; $column++) {
            $rawHeaderValue = trim((string) $this->getCellValue($sheet, $column, $headerRow));
            $headerValue = strtolower($rawHeaderValue);

            if (
                (strpos($headerValue, 'order') !== false && strpos($headerValue, 'id') !== false)
                || strpos($rawHeaderValue, '订单号') !== false
                || strpos($rawHeaderValue, '订单编号') !== false
            ) {
                $indices['order_id'] = $column;
            } elseif ($headerValue === 'sku') {
                $indices['sku'] = $column;
            } elseif (
                strpos($headerValue, 'spec') !== false
                || strpos($headerValue, 'attribute') !== false
                || strpos($rawHeaderValue, '产品规格') !== false
                || strpos($rawHeaderValue, '规格') !== false
                || strpos($rawHeaderValue, '属性') !== false
            ) {
                $indices['specs'] = $column;
            } elseif (
                strpos($rawHeaderValue, '销售链接') !== false
                || strpos($rawHeaderValue, '产品链接') !== false
                || strpos($headerValue, 'sales link') !== false
                || strpos($headerValue, 'product link') !== false
            ) {
                $indices['product_link'] = $column;
            } elseif (
                strpos($headerValue, 'picture') !== false
                || strpos($headerValue, 'image') !== false
                || strpos($rawHeaderValue, '产品图片') !== false
                || strpos($rawHeaderValue, '图片') !== false
                || strpos($rawHeaderValue, '款图') !== false
            ) {
                $indices['picture'] = $column;
            } elseif (
                strpos($headerValue, 'quantity') !== false
                || strpos($headerValue, 'qty') !== false
                || strpos($rawHeaderValue, '产品总数') !== false
                || strpos($rawHeaderValue, '总数') !== false
                || strpos($rawHeaderValue, '产品数量') !== false
                || strpos($rawHeaderValue, '数量') !== false
            ) {
                $indices['quantity'] = $column;
            }
        }

        return $indices;
    }

    private function getSourceDataColumnCount($sheet, $headerRow)
    {
        $highestColumn = $sheet->getHighestColumn();
        $columnCount = PHPExcel_Cell::columnIndexFromString($highestColumn);
        $lastUsedColumn = -1;

        for ($column = 0; $column < $columnCount; $column++) {
            if (!$this->isBlank($this->getCellValue($sheet, $column, $headerRow))) {
                $lastUsedColumn = $column;
            }
        }

        return $lastUsedColumn + 1;
    }

    private function getCellValue($sheet, $column, $row)
    {
        if ($column === null) {
            return null;
        }

        $cell = $sheet->getCellByColumnAndRow($column, $row);

        try {
            return $cell->getCalculatedValue();
        } catch (\Exception $e) {
            \Log::warning('Falling back to raw Excel cell value after formula calculation failed.', [
                'sheet' => $sheet->getTitle(),
                'cell' => PHPExcel_Cell::stringFromColumnIndex($column) . $row,
                'error' => $e->getMessage(),
            ]);

            return $this->getFormulaFallbackValue($cell);
        }
    }

    private function getFormulaFallbackValue($cell)
    {
        $oldValue = method_exists($cell, 'getOldCalculatedValue') ? $cell->getOldCalculatedValue() : null;

        if (!$this->isBlank($oldValue) && !$this->looksLikeFormula($oldValue)) {
            return $oldValue;
        }

        $rawValue = $cell->getValue();

        if ($this->isDispimgFormula($rawValue) || $this->isDispimgFormula($oldValue)) {
            return '';
        }

        if (!$this->looksLikeFormula($rawValue)) {
            return $rawValue;
        }

        return '';
    }

    private function looksLikeFormula($value)
    {
        return is_string($value) && strpos(trim($value), '=') === 0;
    }

    private function isDispimgFormula($value)
    {
        return is_string($value) && stripos($value, 'DISPIMG(') !== false;
    }

    private function isSourceRowBlank($sheet, $row, $columnCount)
    {
        for ($column = 0; $column < $columnCount; $column++) {
            if (!$this->isBlank($this->getCellValue($sheet, $column, $row))) {
                return false;
            }
        }

        return true;
    }

    private function isBlank($value)
    {
        return $value === null || trim((string) $value) === '';
    }

    private function normalizeLookupKey($value)
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return trim((string) $value);
    }

    private function extractFilenameKey($filename)
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $parts = explode('_', $baseName);

        if (count($parts) > 1) {
            return implode('_', array_slice($parts, 1));
        }

        return $baseName;
    }

    private function generateAllOutputFilename($sourceFilename)
    {
        return $this->withXlsxExtension('order_output_all' . $this->extractFilenameKey($sourceFilename));
    }

    private function generateCtcxOutputFilename($sourceFilename)
    {
        return $this->withXlsxExtension('order_output_彩图刺绣' . $this->extractFilenameKey($sourceFilename));
    }

    private function generateTemplateOutputFilename($templateLabel, $sourceFilename)
    {
        return $this->withXlsxExtension('order_output_' . $templateLabel . $this->extractFilenameKey($sourceFilename));
    }

    private function generateArchiveFilename($filenameKey)
    {
        $baseName = pathinfo($this->withXlsxExtension($filenameKey), PATHINFO_FILENAME);

        return 'order_outputs_' . $baseName . '.zip';
    }

    private function withXlsxExtension($filename)
    {
        if (preg_match('/\.(xlsx|xls|csv)$/i', $filename)) {
            return preg_replace('/\.(xlsx|xls|csv)$/i', '.xlsx', $filename);
        }

        return $filename . '.xlsx';
    }

    private function getProcessedFilePath($filename)
    {
        return storage_path('app/public/processed_files/' . $filename);
    }

    private function getIntermediateFilePath($filename)
    {
        return storage_path('app/temp/processed_files/' . uniqid('run_', true) . '/' . $filename);
    }

    private function saveExcelWriter(PHPExcel_Writer_Excel2007 $writer, $outputPath)
    {
        $this->ensureDirectory(dirname($outputPath));
        $temporaryPath = $this->createTemporaryPath('excel_', '.xlsx');

        try {
            $writer->save($temporaryPath);

            if (!copy($temporaryPath, $outputPath)) {
                throw new \Exception("Unable to copy Excel file to: {$outputPath}");
            }
        } finally {
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function createTemporaryPath($prefix, $extension)
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), $prefix);

        if ($temporaryPath === false) {
            throw new \Exception('Unable to create temporary file.');
        }

        $pathWithExtension = $temporaryPath . $extension;

        if (!rename($temporaryPath, $pathWithExtension)) {
            @unlink($temporaryPath);
            throw new \Exception('Unable to create temporary file with extension.');
        }

        return $pathWithExtension;
    }

    private function writeTextCell($sheet, $column, $row, $value)
    {
        $sheet->setCellValueExplicitByColumnAndRow(
            $column,
            $row,
            (string) $value,
            PHPExcel_Cell_DataType::TYPE_STRING
        );
    }

    private function ensureDirectory($directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function getEmbeddedImagesByRow($sourceFilePath)
    {
        $result = [
            'by_row' => [],
            'paths' => [],
        ];

        if (!preg_match('/\.xlsx$/i', $sourceFilePath)) {
            return $result;
        }

        $zip = new ZipArchive();

        if ($zip->open($sourceFilePath) !== true) {
            return $result;
        }

        try {
            $drawingPaths = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if (preg_match('#^xl/drawings/drawing\d+\.xml$#', $entry)) {
                    $drawingPaths[] = $entry;
                }
            }

            $mediaCache = [];

            foreach ($drawingPaths as $drawingPath) {
                $drawingXml = $zip->getFromName($drawingPath);

                if ($drawingXml === false) {
                    continue;
                }

                $drawing = simplexml_load_string($drawingXml);

                if ($drawing === false) {
                    continue;
                }

                $relationships = $this->getDrawingRelationships($zip, $drawingPath);

                if (empty($relationships)) {
                    continue;
                }

                $drawing->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
                $drawing->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $anchors = $drawing->xpath('//xdr:twoCellAnchor|//xdr:oneCellAnchor') ?: [];

                foreach ($anchors as $anchor) {
                    $anchor->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
                    $anchor->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

                    $rowNodes = $anchor->xpath('xdr:from/xdr:row') ?: [];
                    $blipNodes = $anchor->xpath('.//a:blip') ?: [];

                    if (empty($rowNodes) || empty($blipNodes)) {
                        continue;
                    }

                    $row = ((int) $rowNodes[0]) + 1;
                    $attributes = $blipNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $relationshipId = (string) ($attributes['embed'] ?? '');

                    if ($relationshipId === '' || !isset($relationships[$relationshipId])) {
                        continue;
                    }

                    $mediaPath = $relationships[$relationshipId];

                    if (!isset($mediaCache[$mediaPath])) {
                        $bytes = $zip->getFromName($mediaPath);

                        if ($bytes === false) {
                            continue;
                        }

                        $extension = pathinfo($mediaPath, PATHINFO_EXTENSION) ?: 'image';
                        $directory = storage_path('app/temp/source_images');
                        $this->ensureDirectory($directory);
                        $tempPath = $directory . DIRECTORY_SEPARATOR . uniqid('source_', true) . '.' . $extension;
                        file_put_contents($tempPath, $bytes);

                        $mediaCache[$mediaPath] = $tempPath;
                        $result['paths'][] = $tempPath;
                    }

                    if (!isset($result['by_row'][$row])) {
                        $result['by_row'][$row] = $mediaCache[$mediaPath];
                    }
                }
            }

            $cellImagePathsById = $this->getCellImagePathsById($zip, $result);

            if (!empty($cellImagePathsById)) {
                $this->appendDispimgImagesByRow($sourceFilePath, $cellImagePathsById, $result);
            }
        } finally {
            $zip->close();
        }

        return $result;
    }

    private function getCellImagePathsById(ZipArchive $zip, array &$result)
    {
        $cellImagesXml = $zip->getFromName('xl/cellimages.xml');

        if ($cellImagesXml === false) {
            return [];
        }

        $relationships = $this->getCellImageRelationships($zip);

        if (empty($relationships)) {
            return [];
        }

        $cellImages = simplexml_load_string($cellImagesXml);

        if ($cellImages === false) {
            return [];
        }

        $cellImages->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
        $cellImages->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $pictures = $cellImages->xpath('//xdr:pic') ?: [];
        $pathsById = [];
        $mediaCache = [];

        foreach ($pictures as $picture) {
            $picture->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $picture->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $nameNodes = $picture->xpath('xdr:nvPicPr/xdr:cNvPr') ?: [];
            $blipNodes = $picture->xpath('xdr:blipFill/a:blip') ?: [];

            if (empty($nameNodes) || empty($blipNodes)) {
                continue;
            }

            $imageId = (string) ($nameNodes[0]['name'] ?? '');
            $attributes = $blipNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) ($attributes['embed'] ?? '');

            if ($imageId === '' || $relationshipId === '' || !isset($relationships[$relationshipId])) {
                continue;
            }

            $mediaPath = $relationships[$relationshipId];

            if (!isset($mediaCache[$mediaPath])) {
                $bytes = $zip->getFromName($mediaPath);

                if ($bytes === false) {
                    continue;
                }

                $extension = pathinfo($mediaPath, PATHINFO_EXTENSION) ?: 'image';
                $directory = storage_path('app/temp/source_images');
                $this->ensureDirectory($directory);
                $tempPath = $directory . DIRECTORY_SEPARATOR . uniqid('source_cell_', true) . '.' . $extension;
                file_put_contents($tempPath, $bytes);

                $mediaCache[$mediaPath] = $tempPath;
                $result['paths'][] = $tempPath;
            }

            $pathsById[$imageId] = $mediaCache[$mediaPath];
        }

        return $pathsById;
    }

    private function getCellImageRelationships(ZipArchive $zip)
    {
        $relationshipsXml = $zip->getFromName('xl/_rels/cellimages.xml.rels');

        if ($relationshipsXml === false) {
            return [];
        }

        $relationshipsNode = simplexml_load_string($relationshipsXml);

        if ($relationshipsNode === false) {
            return [];
        }

        $relationshipsNode->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationships = [];

        foreach ($relationshipsNode->xpath('//rel:Relationship') ?: [] as $relationship) {
            $id = (string) $relationship['Id'];
            $target = (string) $relationship['Target'];

            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = $this->normalizeZipPath('xl', $target);
        }

        return $relationships;
    }

    private function appendDispimgImagesByRow($sourceFilePath, array $cellImagePathsById, array &$result)
    {
        try {
            $sourceExcel = $this->loadSourceExcel($sourceFilePath);
            $sourceSheet = $sourceExcel->getActiveSheet();
            $highestRow = $sourceSheet->getHighestRow();
            $highestColumn = $sourceSheet->getHighestColumn();
            $columnCount = PHPExcel_Cell::columnIndexFromString($highestColumn);

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($column = 0; $column < $columnCount; $column++) {
                    $cell = $sourceSheet->getCellByColumnAndRow($column, $row);
                    $imageId = $this->extractDispimgImageId($cell->getValue());

                    if ($imageId === null && method_exists($cell, 'getOldCalculatedValue')) {
                        $imageId = $this->extractDispimgImageId($cell->getOldCalculatedValue());
                    }

                    if ($imageId === null || !isset($cellImagePathsById[$imageId]) || isset($result['by_row'][$row])) {
                        continue;
                    }

                    $result['by_row'][$row] = $cellImagePathsById[$imageId];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to read DISPIMG cell images from source workbook: ' . $e->getMessage());
        }
    }

    private function extractDispimgImageId($value)
    {
        if (!is_string($value) || stripos($value, 'DISPIMG(') === false) {
            return null;
        }

        if (preg_match('/DISPIMG\(\s*"([^"]+)"/i', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getDrawingRelationships(ZipArchive $zip, $drawingPath)
    {
        $relationshipsPath = dirname($drawingPath) . '/_rels/' . basename($drawingPath) . '.rels';
        $relationshipsXml = $zip->getFromName($relationshipsPath);

        if ($relationshipsXml === false) {
            return [];
        }

        $relationshipsNode = simplexml_load_string($relationshipsXml);

        if ($relationshipsNode === false) {
            return [];
        }

        $relationshipsNode->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationships = [];

        foreach ($relationshipsNode->xpath('//rel:Relationship') ?: [] as $relationship) {
            $id = (string) $relationship['Id'];
            $target = (string) $relationship['Target'];

            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = $this->normalizeZipPath(dirname($drawingPath), $target);
        }

        return $relationships;
    }

    private function normalizeZipPath($baseDirectory, $target)
    {
        $target = str_replace('\\', '/', $target);

        if (strpos($target, '/') === 0) {
            $path = ltrim($target, '/');
        } else {
            $path = trim($baseDirectory . '/' . $target, '/');
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function setTemplateCellValue($sheet, array $headers, $column, $row, $value, array &$imageTempFiles, $embeddedImagePath = null)
    {
        if ($this->shouldRenderImageColumn($headers, $column)) {
            $this->setCellValueOrImage($sheet, $column, $row, $value, $imageTempFiles, $embeddedImagePath);
        } else {
            $this->writeTextCell($sheet, $column, $row, $value);
        }

        if ($this->shouldWrapTemplateColumn($headers, $column, $value)) {
            $sheet->getStyleByColumnAndRow($column, $row)
                ->getAlignment()
                ->setWrapText(true);
        }
    }

    private function setCellValueOrImage($sheet, $column, $row, $value, array &$imageTempFiles, $embeddedImagePath = null)
    {
        $value = trim((string) $value);

        if ($this->isStandaloneNoThanksImageReference($value)) {
            $this->writeTextCell($sheet, $column, $row, '');
            return;
        }

        if ($embeddedImagePath !== null && file_exists($embeddedImagePath)) {
            $imagePath = $this->prepareImageForExcel($embeddedImagePath, $imageTempFiles);

            if ($imagePath !== null) {
                $this->insertImageIntoCell($sheet, $column, $row, $imagePath);
                return;
            }
        }

        if ($this->isImageFilePath($value)) {
            $imagePath = $this->prepareImageForExcel($value, $imageTempFiles);

            if ($imagePath !== null) {
                $this->insertImageIntoCell($sheet, $column, $row, $imagePath);
                return;
            }
        }

        $mixedImageReferences = $this->extractImageReferences($value);

        if (!empty($mixedImageReferences) && !$this->isSingleImageReference($value, $mixedImageReferences)) {
            $this->setCellValueWithMixedImages($sheet, $column, $row, $value, $mixedImageReferences, $imageTempFiles);
            return;
        }

        if (!$this->isImageUrl($value)) {
            $this->writeTextCell($sheet, $column, $row, $value);
            return;
        }

        $imagePath = $this->downloadImageForExcel($value, $imageTempFiles);

        if ($imagePath === null) {
            $this->writeTextCell($sheet, $column, $row, $value);
            return;
        }

        $this->insertImageIntoCell($sheet, $column, $row, $imagePath);
    }

    private function setCellValueWithMixedImages($sheet, $column, $row, $value, array $imageReferences, array &$imageTempFiles)
    {
        $text = $this->stripImageReferencesFromText($value, $imageReferences);
        $this->writeTextCell($sheet, $column, $row, $text);
        $sheet->getStyleByColumnAndRow($column, $row)->getAlignment()->setWrapText(true);

        $inserted = 0;

        foreach ($imageReferences as $reference) {
            $imagePath = null;

            if ($this->isImageFilePath($reference)) {
                $imagePath = $this->prepareImageForExcel($reference, $imageTempFiles);
            } elseif ($this->isImageUrl($reference)) {
                $imagePath = $this->downloadImageForExcel($reference, $imageTempFiles);
            }

            if ($imagePath === null) {
                continue;
            }

            $this->insertImageIntoCell($sheet, $column, $row, $imagePath, false, 38, 4, 22 + ($inserted * 44));
            $inserted++;
        }

        if ($inserted === 0) {
            $this->writeTextCell($sheet, $column, $row, $value);
            return;
        }

        $sheet->getRowDimension($row)->setRowHeight(max($sheet->getRowDimension($row)->getRowHeight(), 24 + ($inserted * 44)));
    }

    private function insertImageIntoCell($sheet, $column, $row, $imagePath, $clearCell = true, $height = 68, $offsetX = 4, $offsetY = 4)
    {
        $coordinate = PHPExcel_Cell::stringFromColumnIndex($column) . $row;
        $drawing = new \PHPExcel_Worksheet_Drawing();
        $drawing->setName('Product image');
        $drawing->setDescription('Product image');
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinate);
        $drawing->setResizeProportional(true);
        $drawing->setHeight($height);
        $drawing->setOffsetX($offsetX);
        $drawing->setOffsetY($offsetY);
        $drawing->setWorksheet($sheet);

        if ($clearCell) {
            $this->writeTextCell($sheet, $column, $row, '');
        }

        $sheet->getRowDimension($row)->setRowHeight(max($sheet->getRowDimension($row)->getRowHeight(), $height - 10));
    }

    private function isImageUrl($value)
    {
        if (!is_string($value) || !preg_match('/^https?:\/\//i', $value)) {
            return false;
        }

        return !$this->isNoThanksImageReference($value);
    }

    private function isImageFilePath($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        if ($this->isNoThanksImageReference($value)) {
            return false;
        }

        return file_exists($value) && @getimagesize($value) !== false;
    }

    private function isNoThanksImageReference($value)
    {
        $value = strtolower(trim((string) $value));

        return strpos($value, 'no-thanks') !== false
            || strpos($value, 'no_thanks') !== false
            || strpos($value, 'no thanks') !== false
            || strpos($value, 'no-thank') !== false
            || strpos($value, 'no_thank') !== false
            || strpos($value, 'no thank') !== false;
    }

    private function isStandaloneNoThanksImageReference($value)
    {
        $value = trim((string) $value);

        if (!$this->isNoThanksImageReference($value)) {
            return false;
        }

        return preg_match('/^https?:\/\//i', $value)
            || preg_match('/\.(png|jpe?g|gif|webp)$/i', $value)
            || file_exists($value);
    }

    private function extractImageReferences($value)
    {
        $references = [];

        if (!is_string($value) || trim($value) === '') {
            return $references;
        }

        if (preg_match_all('/https?:\/\/[^\s]+/i', $value, $matches)) {
            foreach ($matches[0] as $match) {
                $references[] = rtrim($match, " \t\r\n,，;；");
            }
        }

        foreach (preg_split('/\r\n|\n|\r/', $value) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $candidates = [$line];

            if (strpos($line, '：') !== false) {
                $parts = explode('：', $line, 2);
                $candidates[] = trim($parts[1]);
            }

            if (preg_match('/^.+?:\s*([A-Za-z]:[\/\\\\].+)$/', $line, $matches)) {
                $candidates[] = trim($matches[1]);
            }

            foreach ($candidates as $candidate) {
                if ($this->isImageFilePath($candidate)) {
                    $references[] = $candidate;
                }
            }
        }

        return array_values(array_unique($references));
    }

    private function isSingleImageReference($value, array $references)
    {
        return count($references) === 1 && trim((string) $value) === $references[0];
    }

    private function stripImageReferencesFromText($value, array $references)
    {
        $text = (string) $value;

        foreach ($references as $reference) {
            $text = str_replace($reference, '', $text);
        }

        $lines = [];

        foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function downloadImageForExcel($url, array &$imageTempFiles)
    {
        if (array_key_exists($url, $imageTempFiles['cache'])) {
            return $imageTempFiles['cache'][$url];
        }

        try {
            $bytes = $this->fetchUrlBytes($url);

            if ($bytes === null || $bytes === '') {
                $imageTempFiles['cache'][$url] = null;
                return null;
            }

            $directory = storage_path('app/temp/export_images');
            $this->ensureDirectory($directory);
            $downloadPath = $directory . DIRECTORY_SEPARATOR . sha1($url . microtime(true)) . '.image';
            file_put_contents($downloadPath, $bytes);
            $imageTempFiles['paths'][] = $downloadPath;

            $path = $this->prepareImageForExcel($downloadPath, $imageTempFiles);
            $imageTempFiles['cache'][$url] = $path;

            return $path;
        } catch (\Exception $e) {
            \Log::warning('Failed to embed product image: ' . $e->getMessage());
            $imageTempFiles['cache'][$url] = null;
            return null;
        }
    }

    private function prepareImageForExcel($sourcePath, array &$imageTempFiles)
    {
        if (!file_exists($sourcePath)) {
            return null;
        }

        $imageInfo = @getimagesize($sourcePath);

        if ($imageInfo === false || !isset($imageInfo[2])) {
            return null;
        }

        $currentExtension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

        if ($imageInfo[2] === IMAGETYPE_JPEG) {
            $normalizedPath = $this->normalizeJpegOrientationForExcel($sourcePath, $imageTempFiles);

            if ($normalizedPath !== null) {
                return $normalizedPath;
            }
        }

        if (in_array($currentExtension, $allowedExtensions, true)) {
            return $sourcePath;
        }

        $extension = image_type_to_extension($imageInfo[2], false);

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $directory = storage_path('app/temp/export_images');
        $this->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . sha1($sourcePath . microtime(true)) . '.' . $extension;
        copy($sourcePath, $path);

        $imageTempFiles['paths'][] = $path;

        return $path;
    }

    private function normalizeJpegOrientationForExcel($sourcePath, array &$imageTempFiles)
    {
        $orientation = $this->readJpegExifOrientation($sourcePath);

        if ($orientation <= 1) {
            return null;
        }

        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
            return null;
        }

        $image = @imagecreatefromjpeg($sourcePath);

        if ($image === false) {
            return null;
        }

        $normalized = $this->applyExifOrientationToImage($image, $orientation);

        if ($normalized === false) {
            imagedestroy($image);
            return null;
        }

        if ($normalized !== $image) {
            imagedestroy($image);
            $image = $normalized;
        }

        $directory = storage_path('app/temp/export_images');
        $this->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . sha1($sourcePath . $orientation . microtime(true)) . '.jpg';

        if (!imagejpeg($image, $path, 92)) {
            imagedestroy($image);
            return null;
        }

        imagedestroy($image);
        $imageTempFiles['paths'][] = $path;

        return $path;
    }

    private function applyExifOrientationToImage($image, $orientation)
    {
        switch ((int) $orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3:
                return imagerotate($image, 180, 0);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return imagerotate($image, -90, 0);
            case 6:
                return imagerotate($image, -90, 0);
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return imagerotate($image, 90, 0);
            case 8:
                return imagerotate($image, 90, 0);
            default:
                return $image;
        }
    }

    private function readJpegExifOrientation($sourcePath)
    {
        $bytes = @file_get_contents($sourcePath, false, null, 0, 65536);

        if (!is_string($bytes) || substr($bytes, 0, 2) !== "\xFF\xD8") {
            return 1;
        }

        $offset = 2;
        $length = strlen($bytes);

        while ($offset + 4 <= $length) {
            if (ord($bytes[$offset]) !== 0xFF) {
                break;
            }

            while ($offset < $length && ord($bytes[$offset]) === 0xFF) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $marker = ord($bytes[$offset]);
            $offset++;

            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            if ($offset + 2 > $length) {
                break;
            }

            $segmentLength = unpack('n', substr($bytes, $offset, 2))[1];
            $segmentStart = $offset + 2;
            $payloadLength = $segmentLength - 2;

            if ($payloadLength <= 0 || $segmentStart + $payloadLength > $length) {
                break;
            }

            if ($marker === 0xE1) {
                $orientation = $this->readExifOrientationFromApp1Segment(substr($bytes, $segmentStart, $payloadLength));

                if ($orientation > 0) {
                    return $orientation;
                }
            }

            $offset += $segmentLength;
        }

        return 1;
    }

    private function readExifOrientationFromApp1Segment($segment)
    {
        if (substr($segment, 0, 6) !== "Exif\0\0") {
            return 0;
        }

        $tiff = substr($segment, 6);
        $endian = substr($tiff, 0, 2);

        if ($endian === 'II') {
            $shortFormat = 'v';
            $longFormat = 'V';
        } elseif ($endian === 'MM') {
            $shortFormat = 'n';
            $longFormat = 'N';
        } else {
            return 0;
        }

        if (strlen($tiff) < 14 || $this->unpackValue($shortFormat, substr($tiff, 2, 2)) !== 42) {
            return 0;
        }

        $ifdOffset = $this->unpackValue($longFormat, substr($tiff, 4, 4));

        if ($ifdOffset < 8 || $ifdOffset + 2 > strlen($tiff)) {
            return 0;
        }

        $entryCount = $this->unpackValue($shortFormat, substr($tiff, $ifdOffset, 2));
        $entryOffset = $ifdOffset + 2;

        for ($index = 0; $index < $entryCount; $index++) {
            $currentOffset = $entryOffset + ($index * 12);

            if ($currentOffset + 12 > strlen($tiff)) {
                break;
            }

            $tag = $this->unpackValue($shortFormat, substr($tiff, $currentOffset, 2));

            if ($tag !== 0x0112) {
                continue;
            }

            return $this->unpackValue($shortFormat, substr($tiff, $currentOffset + 8, 2));
        }

        return 0;
    }

    private function unpackValue($format, $bytes)
    {
        $values = unpack($format, $bytes);

        return (int) $values[1];
    }

    private function fetchUrlBytes($url)
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);

            $bytes = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($bytes === false || $status >= 400) {
                throw new \Exception($error ?: "HTTP {$status} while downloading image");
            }

            return $bytes;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: Mozilla/5.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function cleanupTempFiles(array $imageTempFiles)
    {
        $this->cleanupTempPaths($imageTempFiles['paths'] ?? []);
    }

    private function cleanupTempPaths(array $paths)
    {
        foreach ($paths as $path) {
            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function createDownloadArchive(array $outputFiles, $filenameKey)
    {
        $archiveFilename = $this->generateArchiveFilename($filenameKey);
        $archivePath = $this->getProcessedFilePath($archiveFilename);
        $temporaryPath = $this->createTemporaryPath('archive_', '.zip');
        $this->ensureDirectory(dirname($archivePath));

        if (file_exists($archivePath)) {
            @unlink($archivePath);
        }

        $zip = new ZipArchive();

        try {
            if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Unable to create archive: {$temporaryPath}");
            }

            foreach ($outputFiles as $file) {
                if (!$zip->addFile($file['path'], $file['filename'])) {
                    throw new \Exception("Unable to add file to archive: {$file['path']}");
                }
            }

            if ($zip->close() === false) {
                throw new \Exception("Unable to close archive: {$temporaryPath}");
            }

            if (!copy($temporaryPath, $archivePath)) {
                throw new \Exception("Unable to copy archive to: {$archivePath}");
            }
        } finally {
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        return [
            'filename' => $archiveFilename,
            'path' => $archivePath,
        ];
    }

    private function getCtcxHeaders()
    {
        return $this->templateRegistry->forChineseName('彩图刺绣')->headers();
    }

    private function adjustColumnWidths($sheet, $columnCount)
    {
        for ($column = 0; $column < $columnCount; $column++) {
            $letter = PHPExcel_Cell::stringFromColumnIndex($column);
            $sheet->getColumnDimension($letter)->setWidth($column === 0 ? 20 : 10);
        }
    }

    private function adjustImageColumns($sheet, array $headers)
    {
        foreach ($headers as $column => $header) {
            if ($this->shouldRenderImageColumn($headers, $column)) {
                $sheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($column))->setWidth(18);
            }
        }
    }

    private function adjustReviewColumns($sheet, array $headers)
    {
        foreach ($headers as $column => $header) {
            if ($header === '产品规格') {
                $sheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($column))->setWidth(45);
            }

            if ($header === 'sku' || $header === 'cleaned_sku') {
                $sheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($column))->setWidth(22);
            }

            if ($header === '产品链接') {
                $sheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($column))->setWidth(45);
            }
        }
    }

    private function shouldRenderImageColumn(array $headers, $column)
    {
        if (!isset($headers[$column]) || $this->isProductSpecsColumn($headers, $column)) {
            return false;
        }

        $header = (string) $headers[$column];

        if ($header === 'sku' || $header === 'cleaned_sku') {
            return false;
        }

        if ($column === 2) {
            return true;
        }

        foreach (['图片', '图标', '符号', '字体', '设计稿', '设计风格', '主图', '款图', '款式图', '产品图', '订单图片', '贺卡', '礼品', '包装', 'logo', 'Logo', 'LOGO'] as $needle) {
            if (strpos($header, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function shouldWrapTemplateColumn(array $headers, $column, $value)
    {
        return $this->isProductSpecsColumn($headers, $column)
            || ($column === 19 && !empty($value))
            || (is_string($value) && strpos($value, "\n") !== false);
    }

    private function isProductSpecsColumn(array $headers, $column)
    {
        return isset($headers[$column]) && $headers[$column] === '产品规格';
    }
}
