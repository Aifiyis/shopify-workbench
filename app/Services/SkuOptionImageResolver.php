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

        if ($this->isIgnoredPlaceholderValue($optionValue)) {
            return '';
        }

        foreach ($this->productsById as $productId => $product) {
            if ($this->normalizeSku($product['cleaned_sku'] ?? '') !== $cleanedSku
                && $this->normalizeSku($product['sku'] ?? '') !== $cleanedSku
                && $this->normalizeSku($product['original_sku'] ?? '') !== $cleanedSku) {
                continue;
            }

            $options = $this->optionsByProductId[$productId] ?? [];

            foreach ($options as $option) {
                if ($this->normalizeText($option['option_name'] ?? '') !== $optionNameKey) {
                    continue;
                }

                if ($this->normalizeText($option['image_value'] ?? '') !== $optionValueKey) {
                    continue;
                }

                return $this->imageReference($option);
            }

            foreach ($options as $option) {
                if (!$this->optionNamesAreCompatible($optionNameKey, $this->normalizeText($option['option_name'] ?? ''))) {
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
            if ($this->isNoThanksImageReference($imagePath)) {
                return '';
            }

            $absolutePath = $this->absoluteImagePath($imagePath);

            if ($absolutePath !== '' && file_exists($absolutePath)) {
                if ($this->isNoThanksImageReference($absolutePath)) {
                    return '';
                }

                return $absolutePath;
            }
        }

        $sourceImageUrl = trim((string) ($option['source_image_url'] ?? ''));

        if ($this->isNoThanksImageReference($sourceImageUrl)) {
            return '';
        }

        return $sourceImageUrl;
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
        $value = preg_replace('/\s*[\(\x{FF08}]\s*\+\s*(?:\$|usd)?\s*[\d.,]+.*?[\)\x{FF09}]\s*/iu', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function optionNamesAreCompatible($requestedName, $candidateName)
    {
        $requestedCategory = $this->optionNameCategory($requestedName);
        $candidateCategory = $this->optionNameCategory($candidateName);

        if ($requestedCategory === '' || $candidateCategory === '') {
            return false;
        }

        if ($requestedCategory === $candidateCategory) {
            return true;
        }

        if ($requestedCategory === 'icon') {
            return in_array($candidateCategory, ['icon', 'left_icon', 'right_icon'], true);
        }

        if ($candidateCategory === 'icon') {
            return in_array($requestedCategory, ['icon', 'left_icon', 'right_icon'], true);
        }

        return false;
    }

    private function optionNameCategory($name)
    {
        if (strpos($name, 'greeting card') !== false) {
            return 'greeting_card';
        }

        if (strpos($name, 'gift bag') !== false) {
            return 'gift_bag';
        }

        if (strpos($name, 'icon') !== false || strpos($name, 'pattern') !== false) {
            if (strpos($name, 'left sleeve') !== false) {
                return 'left_icon';
            }

            if (strpos($name, 'right sleeve') !== false) {
                return 'right_icon';
            }

            return 'icon';
        }

        return '';
    }

    private function isIgnoredPlaceholderValue($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s*[\(\x{FF08}]\s*\+\s*(?:\$|usd)?\s*[\d.,]+.*?[\)\x{FF09}]\s*/iu', ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = trim($value);

        return $value === 'yes'
            || $value === 'no'
            || $value === 'no thank'
            || $value === 'no thanks'
            || $value === 'no thank you';
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
}
