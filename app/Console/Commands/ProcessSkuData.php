<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;

class ProcessSkuData extends Command
{
    protected $signature = 'sku:process';
    protected $description = 'Clean SKU data from all-sku-to-product_type.json';

    private $jsonHeaders = [
        'original_sku',
        'cleaned_sku',
        '中文名称',
        '上品人',
        '工艺',
        '处理人',
    ];

    public function handle()
    {
        $this->info('Start cleaning SKU data...');

        try {
            $sourcePath = storage_path('app/private/all-sku-to-product_type.json');
            $excludePath = storage_path('app/private/sku-exclude-values.json');
            $outputJsonPath = storage_path('app/private/sku-cleaned.json');
            $outputXlsxPath = storage_path('app/private/sku-cleaned.xlsx');

            $sourceRows = $this->readJsonFile($sourcePath);
            $excludeValues = $this->loadExcludeValues($excludePath);

            $this->info('Source records: ' . count($sourceRows));
            $this->info('Exclude values: ' . count($excludeValues));

            $cleanedRows = [];

            foreach ($sourceRows as $row) {
                $originalSku = trim((string) ($row['sku'] ?? ''));

                if ($originalSku === '') {
                    continue;
                }

                $cleanedRows[] = [
                    'original_sku' => $originalSku,
                    'cleaned_sku' => $this->cleanSku($originalSku, $excludeValues),
                    '中文名称' => (string) ($row['中文名称'] ?? ''),
                    '上品人' => (string) ($row['上品人'] ?? ''),
                    '工艺' => (string) ($row['工艺'] ?? ''),
                    '处理人' => (string) ($row['处理人'] ?? ''),
                ];
            }

            file_put_contents(
                $outputJsonPath,
                json_encode($cleanedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $uniqueRows = $this->buildUniqueRows($cleanedRows);
            $this->writeWorkbook($cleanedRows, $uniqueRows, $outputXlsxPath);

            $this->line('');
            $this->line('========== SKU Cleaning Summary ==========');
            $this->line('Original rows: ' . count($sourceRows));
            $this->line('Cleaned JSON rows: ' . count($cleanedRows));
            $this->line('Unique sheet rows: ' . count($uniqueRows));
            $this->line('JSON output: ' . $outputJsonPath);
            $this->line('XLSX output: ' . $outputXlsxPath);
            $this->line('==========================================');

            $this->info('SKU cleaning complete.');
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function readJsonFile($path)
    {
        if (!file_exists($path)) {
            throw new \Exception("JSON file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON {$path}: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \Exception("JSON file must contain an array: {$path}");
        }

        return $data;
    }

    private function loadExcludeValues($path)
    {
        $data = $this->readJsonFile($path);
        $values = [];

        if (array_values($data) === $data) {
            $values = $data;
        } elseif (isset($data['all_exclude_values']) && is_array($data['all_exclude_values'])) {
            $values = $data['all_exclude_values'];
        } elseif (isset($data['exclude_values']) && is_array($data['exclude_values'])) {
            $values = $data['exclude_values'];
        }

        return $this->normalizeValues($values);
    }

    private function normalizeValues(array $values)
    {
        $normalized = [];

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    private function cleanSku($sku, array $excludeValues)
    {
        $parts = explode('-', (string) $sku);
        $keptParts = [];

        if (count($parts) >= 1) {
            $keptParts[] = $parts[0];
        }

        if (count($parts) >= 2) {
            $keptParts[] = $parts[1];
        }

        for ($i = 2; $i < count($parts); $i++) {
            $part = trim((string) $parts[$i]);

            if ($part === '') {
                continue;
            }

            if ($this->shouldAlwaysKeepSkuPart($part)) {
                $keptParts[] = $part;
                continue;
            }

            if ($this->isTShirtPart($part, $parts, $i)) {
                $i++;
                continue;
            }

            if ($this->matchesExcludeValue($part, $excludeValues)) {
                continue;
            }

            $keptParts[] = $part;
        }

        $cleanedSku = implode('-', $keptParts);

        return $cleanedSku === '' ? (string) $sku : $cleanedSku;
    }

    private function isTShirtPart($part, array $parts, $index)
    {
        if (strtolower((string) $part) !== 't' || $index + 1 >= count($parts)) {
            return false;
        }

        $nextPart = trim((string) $parts[$index + 1]);

        return strtolower($nextPart) === 'shirt' || stripos($nextPart, 'shirt') !== false;
    }

    private function matchesExcludeValue($part, array $excludeValues)
    {
        foreach ($excludeValues as $excludeValue) {
            if ($excludeValue !== '' && stripos((string) $part, (string) $excludeValue) !== false) {
                return true;
            }
        }

        return false;
    }

    private function shouldAlwaysKeepSkuPart($part)
    {
        $keepValues = ['CS', 'ACC', 'HM', 'LP', 'OP', 'TY', 'PET', 'CX', 'TH', 'SMY', 'FP', 'MJX','QK1054','QK2172','CHJ8100','QK2431','QK2584','LXJ6182','3733','QK1311','WW24090702'];

        return in_array(strtoupper(trim((string) $part)), $keepValues, true);
    }

    private function buildUniqueRows(array $cleanedRows)
    {
        $groups = [];

        foreach ($cleanedRows as $row) {
            $keyParts = [
                $row['cleaned_sku'],
                $row['中文名称'],
                $row['上品人'],
                $row['工艺'],
                $row['处理人'],
            ];
            $key = implode("\x1F", $keyParts);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'original_sku' => $row['original_sku'],
                    'cleaned_sku' => $row['cleaned_sku'],
                    '中文名称' => $row['中文名称'],
                    '上品人' => $row['上品人'],
                    '工艺' => $row['工艺'],
                    '处理人' => $row['处理人'],
                    'multi-number' => 0,
                ];
            }

            $groups[$key]['multi-number']++;
        }

        return array_values($groups);
    }

    private function writeWorkbook(array $rawRows, array $uniqueRows, $path)
    {
        $spreadsheet = new PHPExcel();
        $rawSheet = $spreadsheet->getActiveSheet();
        $rawSheet->setTitle('原始数据');
        $this->writeRowsToSheet($rawSheet, $this->jsonHeaders, $rawRows);

        $uniqueSheet = $spreadsheet->createSheet();
        $uniqueSheet->setTitle('去重数据');
        $this->writeRowsToSheet($uniqueSheet, array_merge($this->jsonHeaders, ['multi-number']), $uniqueRows);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($spreadsheet, 'Excel2007');
        $writer->save($path);
    }

    private function writeRowsToSheet($sheet, array $headers, array $rows)
    {
        foreach ($headers as $column => $header) {
            $sheet->setCellValueByColumnAndRow($column, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;

            foreach ($headers as $column => $header) {
                $sheet->setCellValueByColumnAndRow($column, $excelRow, $row[$header] ?? '');
            }
        }

        $lastColumn = PHPExcel_Cell::stringFromColumnIndex(count($headers) - 1);
        $highestRow = max(count($rows) + 1, 1);

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}{$highestRow}")
            ->getAlignment()
            ->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A2');

        foreach (range(0, count($headers) - 1) as $column) {
            $sheet->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($column))->setWidth($column < 2 ? 28 : 16);
        }
    }
}
