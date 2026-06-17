<?php

namespace App\Services;

class SkuOptionImageResolver
{
    private $jsonPath;
    private $loaded = false;
    private $productsById = [];
    private $optionsByProductId = [];

    public function __construct($jsonPath = null)
    {
        $this->jsonPath = $jsonPath ?: storage_path('app/private/sku-options-image.json');
    }

    public function resolve($cleanedSku, $optionName, $optionValue)
    {
        $this->load();

        $cleanedSku = $this->normalizeSku($cleanedSku);
        $optionNameKey = $this->normalizeText($optionName);
        $optionValueKey = $this->normalizeText($optionValue);

        if ($cleanedSku === '' || $optionNameKey === '' || $optionValueKey === '') {
            return '';
        }

        foreach ($this->productsById as $productId => $product) {
            if ($this->normalizeSku($product['cleaned_sku'] ?? '') !== $cleanedSku
                && $this->normalizeSku($product['sku'] ?? '') !== $cleanedSku
                && $this->normalizeSku($product['original_sku'] ?? '') !== $cleanedSku) {
                continue;
            }

            foreach ($this->optionsByProductId[$productId] ?? [] as $option) {
                if ($this->normalizeText($option['option_name'] ?? '') !== $optionNameKey) {
                    continue;
                }

                if ($this->normalizeText($option['image_value'] ?? '') !== $optionValueKey) {
                    continue;
                }

                return $this->imageReference($option);
            }
        }

        return '';
    }

    private function load()
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_string($this->jsonPath) || !file_exists($this->jsonPath)) {
            return;
        }

        $data = json_decode(file_get_contents($this->jsonPath), true);

        if (!is_array($data)) {
            return;
        }

        foreach ($data['products'] ?? [] as $product) {
            $id = (string) ($product['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $this->productsById[$id] = $product;
        }

        foreach ($data['options'] ?? [] as $option) {
            $productId = (string) ($option['product_id'] ?? '');

            if ($productId === '') {
                continue;
            }

            if (!isset($this->optionsByProductId[$productId])) {
                $this->optionsByProductId[$productId] = [];
            }

            $this->optionsByProductId[$productId][] = $option;
        }
    }

    private function imageReference(array $option)
    {
        $imagePath = trim((string) ($option['image_path'] ?? ''));

        if ($imagePath !== '') {
            $absolutePath = $this->absoluteImagePath($imagePath);

            if ($absolutePath !== '' && file_exists($absolutePath)) {
                return $absolutePath;
            }
        }

        return trim((string) ($option['source_image_url'] ?? ''));
    }

    private function absoluteImagePath($imagePath)
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $imagePath) || strpos($imagePath, DIRECTORY_SEPARATOR) === 0) {
            if (file_exists($imagePath)) {
                return $imagePath;
            }
        }

        $baseDirectory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, dirname($this->jsonPath));
        $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR);

        return $baseDirectory . DIRECTORY_SEPARATOR . $relativePath;
    }

    private function normalizeSku($value)
    {
        return strtolower(trim((string) $value));
    }

    private function normalizeText($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }
}
