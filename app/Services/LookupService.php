<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PHPExcel_IOFactory;

class LookupService
{
    private $styleLookup = null;
    private $colorLookup = null;
    private $lookupFilePath = 'private/clothe-options-vlookup.xlsx';
    private $jsonCachePath = 'private/lookups/';

    public function __construct()
    {
        $this->ensureJsonCacheDirectory();
    }

    private function ensureJsonCacheDirectory()
    {
        if (!is_dir(storage_path('app/' . $this->jsonCachePath))) {
            mkdir(storage_path('app/' . $this->jsonCachePath), 0755, true);
        }
    }

    public function getStyleLookup()
    {
        if ($this->styleLookup !== null) {
            return $this->styleLookup;
        }

        $cachePath = storage_path('app/' . $this->jsonCachePath . 'style_lookup.json');

        if (file_exists($cachePath)) {
            $this->styleLookup = json_decode(file_get_contents($cachePath), true);
        } else {
            $this->styleLookup = $this->loadLookupSheet('style');
            file_put_contents($cachePath, json_encode($this->styleLookup, JSON_PRETTY_PRINT));
        }

        return $this->styleLookup;
    }

    public function getColorLookup()
    {
        if ($this->colorLookup !== null) {
            return $this->colorLookup;
        }

        $cachePath = storage_path('app/' . $this->jsonCachePath . 'color_lookup.json');

        if (file_exists($cachePath)) {
            $this->colorLookup = json_decode(file_get_contents($cachePath), true);
        } else {
            $this->colorLookup = $this->loadLookupSheet('color');
            file_put_contents($cachePath, json_encode($this->colorLookup, JSON_PRETTY_PRINT));
        }

        return $this->colorLookup;
    }

    public function reloadCache()
    {
        $styleCachePath = storage_path('app/' . $this->jsonCachePath . 'style_lookup.json');
        $colorCachePath = storage_path('app/' . $this->jsonCachePath . 'color_lookup.json');

        if (file_exists($styleCachePath)) {
            unlink($styleCachePath);
        }
        if (file_exists($colorCachePath)) {
            unlink($colorCachePath);
        }

        $this->styleLookup = null;
        $this->colorLookup = null;

        return [
            'style' => $this->getStyleLookup(),
            'color' => $this->getColorLookup(),
        ];
    }

    private function loadLookupSheet($sheetName)
    {
        $filePath = storage_path('app/' . $this->lookupFilePath);

        if (!file_exists($filePath)) {
            throw new \Exception("Lookup file not found: {$filePath}");
        }

        try {
            $excel = PHPExcel_IOFactory::load($filePath);
            $sheet = $excel->getSheetByName($sheetName);

            $lookup = [];
            $highestRow = $sheet->getHighestRow();

            for ($row = 1; $row <= $highestRow; $row++) {
                $cellA = $sheet->getCellByColumnAndRow(0, $row)->getValue();
                $cellB = $sheet->getCellByColumnAndRow(1, $row)->getValue();

                if ($cellA === null || $cellA === '') {
                    continue;
                }

                $lookup[$cellA] = $cellB;
            }

            return $lookup;
        } catch (\Exception $e) {
            \Log::error('Failed to load lookup sheet: ' . $e->getMessage());
            throw $e;
        }
    }

    public function matchStyle($sku, $styleLookup = null)
    {
        if ($styleLookup === null) {
            $styleLookup = $this->getStyleLookup();
        }

        if (empty($sku)) {
            return null;
        }

        foreach ($styleLookup as $key => $value) {
            if (strpos($sku, $key) !== false) {
                return $value;
            }
        }

        return null;
    }

    public function matchColor($specs, $colorLookup = null)
    {
        if ($colorLookup === null) {
            $colorLookup = $this->getColorLookup();
        }

        if (empty($specs)) {
            return null;
        }

        $attributes = $this->parseAttributes($specs);

        foreach ($attributes as $attrName => $attrValue) {
            if (strpos($attrName, 'Color') !== false && isset($colorLookup[$attrValue])) {
                return $colorLookup[$attrValue];
            }
        }

        return null;
    }

    public function extractSize($specs)
    {
        if (empty($specs)) {
            return null;
        }

        $attributes = $this->parseAttributes($specs);
        $count = 0;

        foreach ($attributes as $attrName => $attrValue) {
            if ($count >= 3) {
                break;
            }

            if (strpos($attrName, 'Size') !== false) {
                return $attrValue;
            }

            $count++;
        }

        return null;
    }

    private function parseAttributes($specs)
    {
        $attributes = [];
        $lines = preg_split('/\r\n|\n|\r/', trim($specs));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $attributes[trim($name)] = trim($value);
            }
        }

        return $attributes;
    }
}
