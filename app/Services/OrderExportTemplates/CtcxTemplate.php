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
        $colorTranslator = $context['color_translation_resolver'] ?? null;

        $values = $this->applyOptionNameRules($values, $attributes, $row, $context, $colorLookup, $colorTranslator);

        if (strpos($sku, 'CS-QK0743-CX') !== false) {
            return $this->applyDefaultChestPosition($this->applyQk0743Rules($values, $attributes, $colorLookup, $colorTranslator));
        }

        if (strpos($sku, 'CS-QK2571-CX') !== false) {
            return $this->applyDefaultChestPosition($this->applyQk2571Rules($values, $attributes));
        }

        if (strpos($sku, 'CS-QK2433-CX') !== false) {
            $values = $this->applyQk2433Rules($values);
        }

        return $this->applyDefaultChestPosition($values);
    }

    private function applyOptionNameRules(array $values, array $attributes, array $row, array $context, array $colorLookup, $colorTranslator)
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

            $lineColorNumber = $this->lineColorNumberFromOptionName($lowerName);
            if ($lineColorNumber !== null) {
                $colorValues[$lineColorNumber] = $this->translateColorList($value, $colorLookup, $colorTranslator);
            }
        }

        if ($this->hasAnyLineValue($lineValues)) {
            $this->setHeaderValueIfBlank($values, '胸部信息', $this->formatThreeLines($lineValues));
        }

        if ($this->hasAnyLineValue($colorValues)) {
            $this->setHeaderValue($values, '胸部信息颜色', $this->formatThreeLines($colorValues));
        }

        if (!empty($fontLines)) {
            $this->setHeaderValueIfBlank($values, '字体信息', implode("\n", $fontLines));
        }

        return $values;
    }

    private function applyDefaultChestPosition(array $values)
    {
        $this->setHeaderValueIfBlank($values, '胸部位置', '胸部中央');

        return $values;
    }

    private function isSecondAndThirdLinePrompt($lowerName)
    {
        return strpos($lowerName, 'add 2nd line & 3rd line text') !== false;
    }

    protected function shouldSkipCommonColorRule($lowerName)
    {
        return $this->lineColorNumberFromOptionName($lowerName) !== null;
    }

    private function lineColorNumberFromOptionName($lowerName)
    {
        if (strpos($lowerName, 'color') === false) {
            return null;
        }

        if (strpos($lowerName, 'initials') !== false) {
            return 1;
        }

        if (strpos($lowerName, '2nd line') !== false || strpos($lowerName, 'second line') !== false) {
            return 2;
        }

        if (strpos($lowerName, '3rd line') !== false || strpos($lowerName, 'third line') !== false) {
            return 3;
        }

        return null;
    }

    private function applyQk0743Rules(array $values, array $attributes, array $colorLookup, $colorTranslator)
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
                $this->setHeaderValueIfBlank($values, '胸部信息颜色', $this->translateLookupValue($attribute['value'], $colorLookup, $colorTranslator));
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

        $this->applyNameLinesToSleeveInfo($values, $attributes, 'left', 'add names on the sleeve');

        return $values;
    }

    private function applyQk2433Rules(array $values)
    {
        if (!$this->hasSleeveContent($values)) {
            return $values;
        }

        $chestColorIndex = $this->headerIndexOrFallback('鑳搁儴淇℃伅棰滆壊', 20);
        $sleeveColor = $this->sleeveColorFromChestInfoColors((string) ($values[$chestColorIndex] ?? ''));

        if ($sleeveColor !== '') {
            $this->setHeaderOrFallbackValue($values, '琚栧瓙缁ｇ嚎棰滆壊', 18, $sleeveColor);
        }

        return $values;
    }

    private function hasSleeveContent(array $values)
    {
        foreach ([
            ['宸﹁淇℃伅', 9],
            ['宸﹁鍥炬爣', 10],
            ['鍙宠淇℃伅', 14],
            ['鍙宠鍥炬爣', 15],
        ] as $candidate) {
            $index = $this->headerIndexOrFallback($candidate[0], $candidate[1]);

            if ($index !== null && trim((string) ($values[$index] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function headerIndexOrFallback($header, $fallbackIndex)
    {
        $index = $this->headerIndex($header);

        return $index !== null ? $index : $fallbackIndex;
    }

    private function setHeaderOrFallbackValue(array &$values, $header, $fallbackIndex, $value)
    {
        $index = $this->headerIndexOrFallback($header, $fallbackIndex);

        if ($index !== null) {
            $values[$index] = $value;
        }
    }

    private function sleeveColorFromChestInfoColors($chestInfoColors)
    {
        $colors = $this->extractChestInfoColors($chestInfoColors);

        if (empty($colors)) {
            return '';
        }

        $counts = [];

        foreach ($colors as $color) {
            $key = mb_strtolower($color, 'UTF-8');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        foreach ($colors as $color) {
            if (($counts[mb_strtolower($color, 'UTF-8')] ?? 0) > 1) {
                return $color;
            }
        }

        foreach ($colors as $color) {
            $lowerColor = mb_strtolower($color, 'UTF-8');

            if (strpos($lowerColor, 'navy') !== false || strpos($color, '海军蓝') !== false) {
                return $color;
            }
        }

        return $colors[count($colors) - 1];
    }

    private function extractChestInfoColors($chestInfoColors)
    {
        $colors = [];
        $lines = preg_split('/\r\n|\n|\r/', (string) $chestInfoColors);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^.*?(?:[:：]|細)/u', '', $line, 1);

            foreach (preg_split('/[,，]/u', $line) as $color) {
                $color = trim($color);

                if ($color !== '') {
                    $colors[] = $color;
                }
            }
        }

        return $colors;
    }

    private function translateColorList($value, array $colorLookup, $colorTranslator)
    {
        $parts = preg_split('/[,，]/', (string) $value);
        $translatedParts = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $translatedParts[] = $this->translateLookupValue($part, $colorLookup, $colorTranslator);
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
        $lines = [];
        $labels = [
            1 => '第一行',
            2 => '第二行',
            3 => '第三行',
        ];

        foreach ($lineValues as $lineNumber => $value) {
            if (trim((string) $value) === '') {
                continue;
            }

            $lines[] = ($labels[$lineNumber] ?? ('第' . $lineNumber . '行')) . '：' . $value;
        }

        return implode("\n", $lines);
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
