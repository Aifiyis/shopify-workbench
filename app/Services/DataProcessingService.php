<?php

namespace App\Services;

use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_IOFactory;
use PHPExcel_Writer_Excel2007;
use ZipArchive;

class DataProcessingService
{
    private $lookupService;

    public function __construct(LookupService $lookupService)
    {
        $this->lookupService = $lookupService;
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
            $skuLookup = $this->getSkuProductTypeLookup();
            $sourceColumns = $this->getSourceColumnIndices($sourceSheet, 1);

            if ($sourceColumns['sku'] === null) {
                throw new \Exception('SKU column not found in source file.');
            }

            $allResult = $this->buildAllOrderFile(
                $sourceSheet,
                $sourceColumns['sku'],
                $sourceColumns['picture'],
                $skuLookup,
                $filenameKey,
                $sourceFilename,
                $embeddedImages['by_row']
            );

            $outputFiles = [$allResult['file']];
            $ctcxRows = $allResult['ctcx_rows'];

            if (!empty($ctcxRows)) {
                $ctcxResult = $this->processOrderFileCTCX($sourceFilePath, $ctcxRows, $sourceFilename, $embeddedImages['by_row']);

                if (!$ctcxResult['success']) {
                    return $ctcxResult;
                }

                $outputFiles[] = [
                    'filename' => $ctcxResult['output_filename'],
                    'path' => $ctcxResult['output_path'],
                ];
            }

            $archive = $this->createDownloadArchive($outputFiles, $filenameKey);
            $this->cleanupTempPaths($embeddedImages['paths']);

            return [
                'success' => true,
                'output_filename' => $archive['filename'],
                'output_path' => $archive['path'],
                'rows_processed' => $allResult['rows_processed'],
                'ctcx_rows_processed' => count($ctcxRows),
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
            $headers = $this->getCtcxHeaders();
            $imageTempFiles = [
                'paths' => [],
                'cache' => [],
            ];

            foreach ($headers as $index => $header) {
                $outputSheet->setCellValueByColumnAndRow($index, 1, $header);
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

                if ($this->isBlank($orderId)) {
                    continue;
                }

                // style和color从private\lookups\color_lookup.json和style_lookup.json中匹配
                $style = $this->lookupService->matchStyle($sku, $styleLookup);
                $color = $this->lookupService->matchColor($productSpecs, $colorLookup);
                $size = $this->lookupService->extractSize($productSpecs);

                $values = [
                    $filenameKey,
                    $orderId,
                    $productImage ?? '',
                    '',
                    $style ?? '',
                    $color ?? '',
                    $size ?? '',
                    $quantity ?? '',
                ];

                $values = $this->applyCtcxSkuRules($values, $sku, $productSpecs, $colorLookup);

                for ($column = 0; $column < count($headers); $column++) {
                    if ($column === 2) {
                        $this->setCellValueOrImage($outputSheet, $column, $outputRow, $values[$column] ?? '', $imageTempFiles, $embeddedImages['by_row'][$row] ?? null);
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

            $outputFilename = $this->generateCtcxOutputFilename($sourceFilename);
            $outputPath = $this->getIntermediateFilePath($outputFilename);
            $this->ensureDirectory(dirname($outputPath));

            $writer = new PHPExcel_Writer_Excel2007($outputSpreadsheet);
            $writer->save($outputPath);
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

    private function applyCtcxSkuRules(array $values, $sku, $productSpecs, array $colorLookup)
    {
        $sku = (string) $sku;
        $attributes = $this->parseOrderedAttributesAfter($productSpecs, 3);

        if (strpos($sku, 'CS-QK0743-CX') !== false) {
            $chestTextLines = [];

            foreach ($attributes as $attribute) {
                $name = strtolower($attribute['name']);

                if (stripos($name, 'state options') !== false) {
                    $chestTextLines[] = '第一行：' . $attribute['value'];
                }

                if (stripos($name, 'year') !== false) {
                    $chestTextLines[] = '第二行：EST. ' . $this->formatCtcxEstYearLine($attribute['value']);
                }

                if (stripos($name, 'thread color') !== false) {
                    $values[21] = $this->translateLookupValue($attribute['value'], $colorLookup);
                }
            }

            if (!empty($chestTextLines)) {
                $values[19] = implode("\n", $chestTextLines);
            }

            $values[26] = '胸部中央';
        }

        if (strpos($sku, 'CS-QK2571-CX') !== false) {
            $values[20] = '全彩';

            foreach ($attributes as $attribute) {
                $name = strtolower($attribute['name']);

                if (stripos($name, 'thread color') !== false) {
                    $values[17] = $attribute['value'];
                }

                if (strpos($name, 'embroidery') !== false && (strpos($name, 'position') !== false) || (strpos($name, 'placement') !== false)) {
                    $values[26] = $this->mapEmbroideryPosition($attribute['value']);
                }

                if (stripos($name, 'photo') !== false) {
                    $values[22] = $attribute['value'];
                }
            }
        }
        return $values;
    }

    private function formatCtcxEstYearLine(string $yearValue): string
    {
        $yearPart = trim($yearValue);

        if (preg_match('/est/i', $yearPart)) {
            $yearPart = preg_replace('/^\s*est\.?\s*/i', '', $yearPart);
            $yearPart = trim($yearPart);
        }

        return $yearPart;
    }

    private function parseOrderedAttributesAfter($specs, $skipCount)
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

    private function translateLookupValue($value, array $lookup)
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

    private function mapEmbroideryPosition($value)
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

    private function loadSourceExcel($sourceFilePath)
    {
        $reader = PHPExcel_IOFactory::createReaderForFile($sourceFilePath);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($sourceFilePath);
    }

    private function buildAllOrderFile($sourceSheet, $skuColumn, $pictureColumn, array $skuLookup, $filenameKey, $sourceFilename, array $embeddedImagesByRow = [])
    {
        $outputSpreadsheet = new PHPExcel();
        $outputSheet = $outputSpreadsheet->getActiveSheet();
        $highestRow = $sourceSheet->getHighestRow();
        $sourceColumnCount = $this->getSourceDataColumnCount($sourceSheet, 1);
        $appendStartColumn = $sourceColumnCount + 1;
        $ctcxRows = [];
        $rowsProcessed = 0;
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];

        $outputSheet->setCellValueByColumnAndRow(0, 1, '导表日期');

        for ($column = 0; $column < $sourceColumnCount; $column++) {
            $outputSheet->setCellValueByColumnAndRow(
                $column + 1,
                1,
                $this->getCellValue($sourceSheet, $column, 1)
            );
        }

        foreach (['中文名称', '工艺', '处理人', '上品人'] as $offset => $header) {
            $outputSheet->setCellValueByColumnAndRow($appendStartColumn + $offset, 1, $header);
        }

        $outputRow = 2;

        for ($row = 2; $row <= $highestRow; $row++) {
            if ($this->isSourceRowBlank($sourceSheet, $row, $sourceColumnCount)) {
                continue;
            }

            $sku = $this->normalizeLookupKey($this->getCellValue($sourceSheet, $skuColumn, $row));
            $skuInfo = $skuLookup[$sku] ?? [];
            $chineseName = $skuInfo['中文名称'] ?? '';

            $outputSheet->setCellValueByColumnAndRow(0, $outputRow, $filenameKey);

            for ($column = 0; $column < $sourceColumnCount; $column++) {
                $value = $this->getCellValue($sourceSheet, $column, $row);

                if ($pictureColumn !== null && $column === $pictureColumn) {
                    $this->setCellValueOrImage($outputSheet, $column + 1, $outputRow, $value, $imageTempFiles, $embeddedImagesByRow[$row] ?? null);
                    continue;
                }

                $outputSheet->setCellValueByColumnAndRow($column + 1, $outputRow, $value);
            }

            $outputSheet->setCellValueByColumnAndRow($appendStartColumn, $outputRow, $chineseName);
            $outputSheet->setCellValueByColumnAndRow($appendStartColumn + 1, $outputRow, $skuInfo['工艺'] ?? '');
            $outputSheet->setCellValueByColumnAndRow($appendStartColumn + 2, $outputRow, $skuInfo['处理人'] ?? '');
            $outputSheet->setCellValueByColumnAndRow($appendStartColumn + 3, $outputRow, $skuInfo['上品人'] ?? '');

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
        $writer->save($outputPath);
        $this->cleanupTempFiles($imageTempFiles);

        return [
            'file' => [
                'filename' => $outputFilename,
                'path' => $outputPath,
            ],
            'rows_processed' => $rowsProcessed,
            'ctcx_rows' => $ctcxRows,
        ];
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

    private function setCellValueOrImage($sheet, $column, $row, $value, array &$imageTempFiles, $embeddedImagePath = null)
    {
        $value = trim((string) $value);

        if ($embeddedImagePath !== null && file_exists($embeddedImagePath)) {
            $imagePath = $this->prepareImageForExcel($embeddedImagePath, $imageTempFiles);

            if ($imagePath !== null) {
                $this->insertImageIntoCell($sheet, $column, $row, $imagePath);
                return;
            }
        }

        if (!$this->isImageUrl($value)) {
            $sheet->setCellValueByColumnAndRow($column, $row, $value);
            return;
        }

        $imagePath = $this->downloadImageForExcel($value, $imageTempFiles);

        if ($imagePath === null) {
            $sheet->setCellValueByColumnAndRow($column, $row, $value);
            return;
        }

        $this->insertImageIntoCell($sheet, $column, $row, $imagePath);
    }

    private function insertImageIntoCell($sheet, $column, $row, $imagePath)
    {
        $coordinate = PHPExcel_Cell::stringFromColumnIndex($column) . $row;
        $drawing = new \PHPExcel_Worksheet_Drawing();
        $drawing->setName('Product image');
        $drawing->setDescription('Product image');
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinate);
        $drawing->setResizeProportional(true);
        $drawing->setHeight(68);
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);

        $sheet->setCellValueByColumnAndRow($column, $row, '');
        $sheet->getRowDimension($row)->setRowHeight(58);
    }

    private function isImageUrl($value)
    {
        if (!is_string($value) || !preg_match('/^https?:\/\//i', $value)) {
            return false;
        }

        return preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $value) === 1
            || strpos($value, 'cdn.shopify.com') !== false;
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
        $this->ensureDirectory(dirname($archivePath));

        if (file_exists($archivePath)) {
            @unlink($archivePath);
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
            throw new \Exception("Unable to create archive: {$archivePath}");
        }

        foreach ($outputFiles as $file) {
            $zip->addFile($file['path'], $file['filename']);
        }

        $zip->close();

        return [
            'filename' => $archiveFilename,
            'path' => $archivePath,
        ];
    }

    private function getCtcxHeaders()
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
            '产品规格'
        ];
    }

    private function adjustColumnWidths($sheet, $columnCount)
    {
        for ($column = 0; $column < $columnCount; $column++) {
            $letter = PHPExcel_Cell::stringFromColumnIndex($column);
            $sheet->getColumnDimension($letter)->setWidth($column === 0 ? 20 : 10);
        }
    }
}
