<?php

namespace App\Services;

class SkuPlacementResolver
{
    private $placementRulesPath;
    private $skuOptionsImagePath;
    private $loaded = false;
    private $positionsBySkuAndWebsite = [];
    private $rulesBySkuAndWebsite = [];
    private $websitesBySku = [];

    public function __construct($placementRulesPath = null, $skuOptionsImagePath = null)
    {
        $this->placementRulesPath = $placementRulesPath ?: $this->defaultPlacementRulesPath();
        $this->skuOptionsImagePath = $skuOptionsImagePath ?: storage_path('app/private/sku-options-image.json');
    }

    public function resolve($cleanedSku, $website = '')
    {
        $rule = $this->resolveRule($cleanedSku, $website);

        return $rule['position'] ?? '';
    }

    public function resolveRule($cleanedSku, $website = '')
    {
        $this->load();

        $skuKey = $this->normalizeSku($cleanedSku);
        $websiteKey = $this->normalizeWebsite($website);

        if ($skuKey === '' || !isset($this->rulesBySkuAndWebsite[$skuKey])) {
            return [];
        }

        if ($websiteKey !== '') {
            return $this->rulesBySkuAndWebsite[$skuKey][$websiteKey] ?? [];
        }

        foreach ($this->websitesBySku[$skuKey] ?? [] as $knownWebsite) {
            if (isset($this->rulesBySkuAndWebsite[$skuKey][$knownWebsite])) {
                return $this->rulesBySkuAndWebsite[$skuKey][$knownWebsite];
            }
        }

        $rules = array_values($this->rulesBySkuAndWebsite[$skuKey]);
        $positions = array_values(array_unique(array_map(function ($rule) {
            return $rule['position'] ?? '';
        }, $rules)));

        return count($positions) === 1 ? $rules[0] : [];
    }

    private function load()
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $this->loadPlacementRules();
        $this->loadSkuOptionImageWebsites();
    }

    private function loadPlacementRules()
    {
        if (!is_string($this->placementRulesPath) || !file_exists($this->placementRulesPath)) {
            return;
        }

        $data = json_decode(file_get_contents($this->placementRulesPath), true);

        if (!is_array($data)) {
            return;
        }

        foreach ($data['rules'] ?? [] as $rule) {
            $websiteKey = $this->normalizeWebsite($rule['website'] ?? '');
            $position = trim((string) ($rule['position'] ?? ''));

            if ($websiteKey === '' || $position === '') {
                continue;
            }

            $ruleData = $rule;
            $ruleData['website'] = $websiteKey;
            $ruleData['position'] = $position;

            foreach (array_unique($rule['cleaned_skus'] ?? []) as $sku) {
                $skuKey = $this->normalizeSku($sku);

                if ($skuKey === '') {
                    continue;
                }

                if (!isset($this->positionsBySkuAndWebsite[$skuKey])) {
                    $this->positionsBySkuAndWebsite[$skuKey] = [];
                }

                if (!isset($this->rulesBySkuAndWebsite[$skuKey])) {
                    $this->rulesBySkuAndWebsite[$skuKey] = [];
                }

                $this->positionsBySkuAndWebsite[$skuKey][$websiteKey] = $position;
                $this->rulesBySkuAndWebsite[$skuKey][$websiteKey] = $ruleData;
            }
        }
    }

    private function loadSkuOptionImageWebsites()
    {
        if (!is_string($this->skuOptionsImagePath) || !file_exists($this->skuOptionsImagePath)) {
            return;
        }

        $data = json_decode(file_get_contents($this->skuOptionsImagePath), true);

        if (!is_array($data)) {
            return;
        }

        foreach ($data['products'] ?? [] as $product) {
            $websiteKey = $this->normalizeWebsite($product['website'] ?? '');

            if ($websiteKey === '') {
                continue;
            }

            foreach (['cleaned_sku', 'sku', 'original_sku'] as $skuField) {
                $skuKey = $this->normalizeSku($product[$skuField] ?? '');

                if ($skuKey === '') {
                    continue;
                }

                if (!isset($this->websitesBySku[$skuKey])) {
                    $this->websitesBySku[$skuKey] = [];
                }

                if (!in_array($websiteKey, $this->websitesBySku[$skuKey], true)) {
                    $this->websitesBySku[$skuKey][] = $websiteKey;
                }
            }
        }
    }

    private function normalizeSku($value)
    {
        return strtolower(trim((string) $value));
    }

    private function defaultPlacementRulesPath()
    {
        return storage_path('app/private/lookups/sku-placement-rules.json');
    }

    private function normalizeWebsite($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/^https?:\/\//', '', $value);
        $value = preg_replace('/^www\./', '', $value);

        return rtrim($value, '/');
    }
}
