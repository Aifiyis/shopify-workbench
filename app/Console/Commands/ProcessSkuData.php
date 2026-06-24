<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;

class ProcessSkuData extends Command
{
    protected $signature = 'sku:process {--rules-only : Only export SKU exclude rule patterns}';
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
        $this->info('Start SKU processing...');

        try {
            $sourcePath = storage_path('app/private/all-sku-to-product_type.json');
            $excludePath = storage_path('app/private/sku-exclude-values.json');
            $outputJsonPath = storage_path('app/private/sku-cleaned.json');
            $outputXlsxPath = storage_path('app/private/sku-cleaned.xlsx');
            $outputRulePath = storage_path('app/private/sku-exclude-rule-patterns.json');
            $outputRuleXlsxPath = storage_path('app/private/sku-cleaned-rule-patterns.xlsx');

            $excludeValues = $this->loadExcludeValues($excludePath);
            $excludeRulePatterns = $this->exportExcludeRulePatterns($excludeValues, $outputRulePath);

            $this->info('Exclude values: ' . count($excludeValues));
            $this->info('Exclude include-type fields: ' . count($excludeRulePatterns['include_type']['fields']));
            $this->info('Exclude equals-type fields: ' . count($excludeRulePatterns['equals_type']['fields']));

            if ($this->option('rules-only')) {
                $this->line('Rule JSON output: ' . $outputRulePath);
                $this->info('SKU exclude rule export complete.');
                return 0;
            }

            $sourceRows = $this->readJsonFile($sourcePath);
            $this->info('Source records: ' . count($sourceRows));

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

            $ruleCleanedRows = $this->buildCleanedRows($sourceRows, function ($sku) use ($excludeRulePatterns) {
                return $this->cleanSkuByRulePatterns($sku, $excludeRulePatterns);
            });
            $ruleUniqueRows = $this->buildUniqueRows($ruleCleanedRows);
            $this->writeWorkbook($ruleCleanedRows, $ruleUniqueRows, $outputRuleXlsxPath);

            $this->line('');
            $this->line('========== SKU Cleaning Summary ==========');
            $this->line('Original rows: ' . count($sourceRows));
            $this->line('Cleaned JSON rows: ' . count($cleanedRows));
            $this->line('Unique sheet rows: ' . count($uniqueRows));
            $this->line('Rule-pattern rows: ' . count($ruleCleanedRows));
            $this->line('Rule-pattern unique sheet rows: ' . count($ruleUniqueRows));
            $this->line('JSON output: ' . $outputJsonPath);
            $this->line('XLSX output: ' . $outputXlsxPath);
            $this->line('Rule JSON output: ' . $outputRulePath);
            $this->line('Rule-pattern XLSX output: ' . $outputRuleXlsxPath);
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

    private function exportExcludeRulePatterns(array $excludeValues, $path)
    {
        $rules = $this->buildExcludeRulePatterns($excludeValues);

        file_put_contents(
            $path,
            json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $rules;
    }

    private function buildExcludeRulePatterns(array $excludeValues)
    {
        $rules = [
            'source' => 'storage/app/private/sku-exclude-values.json:all_exclude_values',
            'scope' => 'Only SKU parts after the first two hyphen-separated parts are checked.',
            'always_keep_equals' => $this->getAlwaysKeepSkuParts(),
            'include_type' => [
                'description' => 'Exclude when a SKU part contains one of these values or matches one of these feature patterns.',
                'feature_patterns' => $this->getIncludeTypeFeaturePatterns(),
                'fields' => [],
            ],
            'equals_type' => [
                'description' => 'Exclude only when a SKU part equals one of these atomic option values.',
                'feature_patterns' => $this->getEqualsTypeFeaturePatterns(),
                'fields' => [],
            ],
        ];

        foreach ($excludeValues as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $type = $this->classifyExcludeRuleType($value);
            $rules[$type]['fields'][] = [
                'value' => $value,
                'regex' => $this->buildExcludeMatchRegex($value, $type),
                'reason' => $this->getExcludeRuleReason($value, $type),
            ];
        }

        $this->appendManualExcludeRulePatterns($rules);

        $rules['summary'] = [
            'total_fields' => count($rules['include_type']['fields']) + count($rules['equals_type']['fields']),
            'include_type_fields' => count($rules['include_type']['fields']),
            'equals_type_fields' => count($rules['equals_type']['fields']),
        ];

        return $rules;
    }

    private function getIncludeTypeFeaturePatterns()
    {
        return [
            [
                'name' => 'dimension_or_unit',
                'regex' => '/(?:\d+(?:\.\d+)?\s*(?:cm|mm|in|inch|oz)|\d+\s*["\'”“‘’]|(?:\d+(?:\.\d+)?\s*(?:x|×|\*)\s*)+\d+(?:\.\d+)?)/iu',
            ],
            [
                'name' => 'compound_option',
                'regex' => '/[\/+&]|\b(?:with|without)\b/iu',
            ],
            [
                'name' => 'apparel_or_material_keyword',
                'regex' => '/(?:shirt|tshirt|hoodie|crewneck|sweatshirt|pajamas|blanket|canvas|wood|wooden|metal|sherpa)/iu',
            ],
            [
                'name' => 'marketing_or_shape_phrase',
                'regex' => '/(?:popular|bestseller|heart|sunflower|round|square|teardrop|retro\s+tray)/iu',
            ],
        ];
    }

    private function appendManualExcludeRulePatterns(array &$rules)
    {
        $manualRules = [
            'include_type' => [
                [
                    'value' => 'kid',
                    'regex' => '/kid/iu',
                    'reason' => 'manual_contains_match',
                ],
            ],
            'equals_type' => [
                [
                    'value' => 'A',
                    'regex' => '/^\s*A\s*$/iu',
                    'reason' => 'manual_exact_match',
                ],
                [
                    'value' => 'B',
                    'regex' => '/^\s*B\s*$/iu',
                    'reason' => 'manual_exact_match',
                ],
                [
                    'value' => 'C',
                    'regex' => '/^\s*C\s*$/iu',
                    'reason' => 'manual_exact_match',
                ],
            ],
        ];

        foreach ($manualRules as $type => $fields) {
            $existing = [];

            foreach ($rules[$type]['fields'] as $field) {
                $existing[strtolower((string) ($field['value'] ?? ''))] = true;
            }

            foreach ($fields as $field) {
                $key = strtolower((string) $field['value']);

                if (isset($existing[$key])) {
                    continue;
                }

                $rules[$type]['fields'][] = $field;
                $existing[$key] = true;
            }
        }
    }

    private function getEqualsTypeFeaturePatterns()
    {
        return [
            [
                'name' => 'plain_number',
                'regex' => '/^\d+$/u',
            ],
            [
                'name' => 'size_code',
                'regex' => '/^(?:XS|S|M|L|XL|\dXL|A\d|\dT)$/iu',
            ],
            [
                'name' => 'simple_atomic_word',
                'regex' => '/^[A-Za-z]+(?:\s+[A-Za-z]+){0,2}$/u',
            ],
        ];
    }

    private function classifyExcludeRuleType($value)
    {
        $value = trim((string) $value);

        if ($this->matchesEqualsTypeFeature($value)) {
            return 'equals_type';
        }

        return 'include_type';
    }

    private function matchesEqualsTypeFeature($value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^\d+$/u', $normalized)) {
            return true;
        }

        if (preg_match('/^(?:XS|S|M|L|XL|\dXL|A\d|\dT)$/iu', $normalized)) {
            return true;
        }

        if ($this->matchesIncludeTypeFeature($normalized)) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z]+(?:\s+[A-Za-z]+){0,2}$/u', $normalized);
    }

    private function matchesIncludeTypeFeature($value)
    {
        $value = trim((string) $value);

        if (preg_match('/(?:\d+(?:\.\d+)?\s*(?:cm|mm|in|inch|oz)|\d+\s*["\'”“‘’]|(?:\d+(?:\.\d+)?\s*(?:x|×|\*)\s*)+\d+(?:\.\d+)?)/iu', $value)) {
            return true;
        }

        if (preg_match('/[\/+&]|\b(?:with|without)\b/iu', $value)) {
            return true;
        }

        if (preg_match('/(?:shirt|tshirt|hoodie|crewneck|sweatshirt|pajamas|blanket|canvas|wood|wooden|metal|sherpa)/iu', $value)) {
            return true;
        }

        return (bool) preg_match('/(?:popular|bestseller|heart|sunflower|round|square|teardrop|retro\s+tray)/iu', $value);
    }

    private function buildExcludeMatchRegex($value, $type)
    {
        $escaped = preg_quote((string) $value, '/');
        $escaped = str_replace(["\r", "\n"], ['\r', '\n'], $escaped);

        if ($type === 'equals_type') {
            return '/^\s*' . $escaped . '\s*$/iu';
        }

        return '/' . $escaped . '/iu';
    }

    private function getExcludeRuleReason($value, $type)
    {
        $value = trim((string) $value);

        if ($type === 'equals_type') {
            if (preg_match('/^\d+$/u', $value)) {
                return 'plain_number_exact_match';
            }

            if (preg_match('/^(?:XS|S|M|L|XL|\dXL|A\d|\dT)$/iu', $value)) {
                return 'size_code_exact_match';
            }

            return 'simple_atomic_word_exact_match';
        }

        if (preg_match('/(?:\d+(?:\.\d+)?\s*(?:cm|mm|in|inch|oz)|\d+\s*["\'”“‘’]|(?:\d+(?:\.\d+)?\s*(?:x|×|\*)\s*)+\d+(?:\.\d+)?)/iu', $value)) {
            return 'dimension_or_unit_contains_match';
        }

        if (preg_match('/[\/+&]|\b(?:with|without)\b/iu', $value)) {
            return 'compound_option_contains_match';
        }

        if (preg_match('/(?:shirt|tshirt|hoodie|crewneck|sweatshirt|pajamas|blanket|canvas|wood|wooden|metal|sherpa)/iu', $value)) {
            return 'apparel_or_material_keyword_contains_match';
        }

        return 'marketing_shape_or_long_phrase_contains_match';
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

    private function buildCleanedRows(array $sourceRows, callable $cleanSkuCallback)
    {
        $cleanedRows = [];

        foreach ($sourceRows as $row) {
            $originalSku = trim((string) ($row['sku'] ?? ''));

            if ($originalSku === '') {
                continue;
            }

            $cleanedRows[] = [
                $this->jsonHeaders[0] => $originalSku,
                $this->jsonHeaders[1] => $cleanSkuCallback($originalSku),
                $this->jsonHeaders[2] => (string) ($row[$this->jsonHeaders[2]] ?? ''),
                $this->jsonHeaders[3] => (string) ($row[$this->jsonHeaders[3]] ?? ''),
                $this->jsonHeaders[4] => (string) ($row[$this->jsonHeaders[4]] ?? ''),
                $this->jsonHeaders[5] => (string) ($row[$this->jsonHeaders[5]] ?? ''),
            ];
        }

        return $cleanedRows;
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

    private function cleanSkuByRulePatterns($sku, array $rulePatterns)
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

            if ($this->matchesExcludeRulePatterns($part, $rulePatterns)) {
                continue;
            }

            $keptParts[] = $part;
        }

        $cleanedSku = implode('-', $keptParts);

        return $cleanedSku === '' ? (string) $sku : $cleanedSku;
    }

    private function matchesExcludeRulePatterns($part, array $rulePatterns)
    {
        foreach ($rulePatterns['equals_type']['fields'] ?? [] as $field) {
            if ($this->matchesRuleField($part, $field, true)) {
                return true;
            }
        }

        foreach ($rulePatterns['include_type']['fields'] ?? [] as $field) {
            if ($this->matchesRuleField($part, $field, false)) {
                return true;
            }
        }

        return false;
    }

    private function matchesRuleField($part, array $field, $equalsOnly)
    {
        $regex = $field['regex'] ?? null;

        if (is_string($regex) && $regex !== '' && @preg_match($regex, '') !== false) {
            return preg_match($regex, (string) $part) === 1;
        }

        $value = (string) ($field['value'] ?? '');

        if ($value === '') {
            return false;
        }

        if ($equalsOnly) {
            return strcasecmp(trim((string) $part), trim($value)) === 0;
        }

        return stripos((string) $part, $value) !== false;
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
        return in_array(strtoupper(trim((string) $part)), $this->getAlwaysKeepSkuParts(), true);
    }

    private function getAlwaysKeepSkuParts()
    {
        return [
            'CS',
            'ACC',
            'HM',
            'LP',
            'OP',
            'TY',
            'PET',
            'CX',
            'TH',
            'SMY',
            'FP',
            'MJX',
            'QK1054',
            'QK2172',
            'CHJ8100',
            'QK2431',
            'QK2584',
            'LXJ6182',
            '6315',
            'QK1311',
            'WW24090702',
            'QK5333',
            'WYJ6326',
            'QK2844',
            '6316',
            '6317'
        ];
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
