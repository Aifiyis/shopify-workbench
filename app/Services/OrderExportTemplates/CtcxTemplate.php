<?php

namespace App\Services\OrderExportTemplates;

class CtcxTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'ctcx';
    }

    public function label()
    {
        return '彩图刺绣';
    }

    public function supportedChineseNames()
    {
        return ['彩图刺绣'];
    }

    public function headers()
    {
        return $this->withProductSpecsHeader([
            '导表日期',
            '订单号',
            '款式图',
            '是否做货',
            '是否发货',
            '款式',
            '衣服颜色',
            '尺码',
            '数量',
            '左袖信息',
            '左袖图标',
            '左袖字体',
            '左袖备注',
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '右袖备注',
            '袖子位置',
            '袖子绣线颜色',
            '胸部信息',
            '胸部信息颜色',
            '胸部图片',
            '胸部位置',
            '全彩/轮廓',
            '字体信息',
            '贺卡',
            '礼品袋',
            '备注',
            '设计稿',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $sku = (string) ($row['sku'] ?? '');
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $colorLookup = $context['color_lookup'] ?? [];

        $values = $this->applyOptionNameRules($values, $attributes, $row, $context, $colorLookup);

        if (strpos($sku, 'CS-QK0743-CX') !== false) {
            return $this->applyQk0743Rules($values, $attributes, $colorLookup);
        }

        if (strpos($sku, 'CS-QK2571-CX') !== false) {
            return $this->applyQk2571Rules($values, $attributes);
        }

        return $values;
    }

    private function applyOptionNameRules(array $values, array $attributes, array $row, array $context, array $colorLookup)
    {
        $lineValues = [
            1 => '',
            2 => '',
            3 => '',
        ];
        $colorValues = [
            1 => '',
            2 => '',
            3 => '',
        ];
        $fontLines = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($this->isSecondAndThirdLinePrompt($lowerName)) {
                continue;
            }

            if ($name === 'Choose State Options') {
                $image = $this->resolveOptionImage($context, $row, $name, $value);
                $this->setHeaderValueIfBlank($values, '胸部图片', $image !== '' ? $image : $value);
            }

            if ($name === 'School Initials') {
                $lineValues[1] = $value;
            }

            if (strpos($lowerName, '2nd line text') !== false) {
                $lineValues[2] = $value;
            }

            if (strpos($lowerName, '3rd line text') !== false) {
                $lineValues[3] = $value;
            }

            if (strpos($lowerName, '2nd line font') !== false || strpos($lowerName, '3rd line font') !== false) {
                $image = $this->resolveOptionImage($context, $row, $name, $value);
                $fontLines[] = $name . '：' . ($image !== '' ? $image : $value);
            }

            if ($name === 'Color for Initials') {
                $colorValues[1] = $this->translateColorList($value, $colorLookup);
            }

            if ($name === 'Color for 2nd Line') {
                $colorValues[2] = $this->translateColorList($value, $colorLookup);
            }

            if ($name === 'Color for 3rd Line') {
                $colorValues[3] = $this->translateColorList($value, $colorLookup);
            }
        }

        if ($this->hasAnyLineValue($lineValues)) {
            $this->setHeaderValueIfBlank($values, '胸部信息', $this->formatThreeLines($lineValues));
        }

        if ($this->hasAnyLineValue($colorValues)) {
            $this->setHeaderValueIfBlank($values, '胸部信息颜色', $this->formatThreeLines($colorValues));
        }

        if (!empty($fontLines)) {
            $this->setHeaderValueIfBlank($values, '字体信息', implode("\n", $fontLines));
        }

        return $values;
    }

    private function isSecondAndThirdLinePrompt($lowerName)
    {
        return strpos($lowerName, 'add 2nd line & 3rd line text') !== false;
    }

    private function applyQk0743Rules(array $values, array $attributes, array $colorLookup)
    {
        $chestTextLines = [];

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);

            if (strpos($name, 'state options') !== false) {
                $chestTextLines[] = '第一行：' . $attribute['value'];
            }

            if (strpos($name, 'year') !== false) {
                $chestTextLines[] = '第二行：EST. ' . $this->formatEstYearPart($attribute['value']);
            }

            if (strpos($name, 'thread color') !== false) {
                $this->setHeaderValueIfBlank($values, '胸部信息颜色', $this->translateLookupValue($attribute['value'], $colorLookup));
            }
        }

        if (!empty($chestTextLines)) {
            $this->setHeaderValueIfBlank($values, '胸部信息', implode("\n", $chestTextLines));
        }

        $this->setHeaderValueIfBlank($values, '胸部位置', '胸部中央');

        return $values;
    }

    private function applyQk2571Rules(array $values, array $attributes)
    {
        $this->setHeaderValueIfBlank($values, '全彩/轮廓', '全彩');

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);

            if (strpos($name, 'thread color') !== false) {
                $this->setHeaderValueIfBlank($values, '袖子绣线颜色', $attribute['value']);
            }

            if ((strpos($name, 'embroidery') !== false && strpos($name, 'position') !== false)
                || strpos($name, 'placement') !== false) {
                $this->setHeaderValueIfBlank($values, '胸部位置', $this->mapEmbroideryPosition($attribute['value']));
            }

            if (strpos($name, 'photo') !== false) {
                $this->setHeaderValueIfBlank($values, '胸部图片', $attribute['value']);
            }
        }

        return $values;
    }

    private function translateColorList($value, array $colorLookup)
    {
        $parts = preg_split('/[,，]/', (string) $value);
        $translatedParts = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $translatedParts[] = $this->translateLookupValue($part, $colorLookup);
        }

        return implode(', ', $translatedParts);
    }

    private function hasAnyLineValue(array $lineValues)
    {
        foreach ($lineValues as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function formatThreeLines(array $lineValues)
    {
        return '第一行：' . ($lineValues[1] ?? '')
            . "\n" . '第二行：' . ($lineValues[2] ?? '')
            . "\n" . '第三行：' . ($lineValues[3] ?? '');
    }

    private function formatEstYearPart($yearValue)
    {
        $yearPart = trim((string) $yearValue);

        if (preg_match('/est/i', $yearPart)) {
            $yearPart = preg_replace('/^\s*est\.?\s*/i', '', $yearPart);
            $yearPart = trim($yearPart);
        }

        return $yearPart;
    }
}
