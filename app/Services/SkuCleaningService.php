<?php

namespace App\Services;

class SkuCleaningService
{
    private $skuCleanedPath;
    private $excludeValuesPath;
    private $originalLookup = null;
    private $cleanedLookup = null;
    private $excludeValues = null;

    public function __construct($skuCleanedPath = null, $excludeValuesPath = null)
    {
        $this->skuCleanedPath = $skuCleanedPath ?: storage_path('app/private/sku-cleaned.json');
        $this->excludeValuesPath = $excludeValuesPath ?: storage_path('app/private/sku-exclude-values.json');
    }

    public function resolve($sku)
    {
        $originalSku = trim((string) $sku);
        $this->loadLookups();

        if ($originalSku !== '' && isset($this->originalLookup[$originalSku])) {
            $row = $this->originalLookup[$originalSku];
            $cleanedSku = trim((string) ($row['cleaned_sku'] ?? $originalSku));

            return $this->buildResolvedSku($originalSku, $cleanedSku, $row);
        }

        $cleanedSku = $this->cleanSku($originalSku);
        $row = $this->cleanedLookup[$cleanedSku] ?? null;

        return $this->buildResolvedSku($originalSku, $cleanedSku, $row);
    }

    public function cleanSku($sku)
    {
        $parts = explode('-', (string) $sku);
        $keptParts = [];
        $excludeValues = $this->loadExcludeValues();

        if (count($parts) >= 1) {
            $keptParts[] = trim((string) $parts[0]);
        }

        if (count($parts) >= 2) {
            $keptParts[] = trim((string) $parts[1]);
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

        $cleanedSku = implode('-', array_filter($keptParts, function ($part) {
            return $part !== '';
        }));

        return $cleanedSku === '' ? (string) $sku : $cleanedSku;
    }

    private function buildResolvedSku($originalSku, $cleanedSku, $row)
    {
        $category = is_array($row) ? (string) ($row['中文名称'] ?? '') : '';

        return [
            'original_sku' => $originalSku,
            'cleaned_sku' => $cleanedSku,
            'excel_category' => $category,
            'type' => $category,
            '中文名称' => $category,
            '工艺' => is_array($row) ? (string) ($row['工艺'] ?? '') : '',
            '处理人' => is_array($row) ? (string) ($row['处理人'] ?? '') : '',
            '上品人' => is_array($row) ? (string) ($row['上品人'] ?? '') : '',
        ];
    }

    private function loadLookups()
    {
        if ($this->originalLookup !== null && $this->cleanedLookup !== null) {
            return;
        }

        $rows = $this->readJsonArray($this->skuCleanedPath);
        $this->originalLookup = [];
        $this->cleanedLookup = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $originalSku = trim((string) ($row['original_sku'] ?? ''));
            $cleanedSku = trim((string) ($row['cleaned_sku'] ?? ''));

            if ($originalSku !== '') {
                $this->originalLookup[$originalSku] = $row;
            }

            if ($cleanedSku !== '' && !isset($this->cleanedLookup[$cleanedSku])) {
                $this->cleanedLookup[$cleanedSku] = $row;
            }
        }
    }

    private function loadExcludeValues()
    {
        if ($this->excludeValues !== null) {
            return $this->excludeValues;
        }

        $data = $this->readJsonArray($this->excludeValuesPath);
        $values = [];

        if (isset($data['all_exclude_values']) && is_array($data['all_exclude_values'])) {
            $values = $data['all_exclude_values'];
        } elseif (isset($data['exclude_values']) && is_array($data['exclude_values'])) {
            $values = $data['exclude_values'];
        } elseif (array_values($data) === $data) {
            $values = $data;
        }

        $this->excludeValues = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $this->excludeValues[] = $value;
            }
        }

        return $this->excludeValues;
    }

    private function readJsonArray($path)
    {
        if (!file_exists($path)) {
            throw new \Exception("JSON file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \Exception("Invalid JSON {$path}: " . json_last_error_msg());
        }

        return $data;
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
            '3733',
            '3734',
            '6315',
            'QK1311',
            'WW24090702',
        ];
    }
}
