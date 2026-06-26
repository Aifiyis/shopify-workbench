<?php

namespace App\Services\OrderExportTemplates;

abstract class AbstractOrderExportTemplate implements OrderExportTemplate
{
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
        ]);
    }

    public function mapRow(array $row, array $context)
    {
        $values = $this->applyCommonOptionRules($this->baseValues($row), $row, $context);
        $values = $this->applyRules($values, $row, $context);
        $this->applySleevePositionColumnRules($values);

        return $values;
    }

    protected function baseValues(array $row)
    {
        $values = array_fill(0, count($this->headers()), '');
        $this->setFirstHeaderValue($values, ['导表日期', '导表时间'], $row['filename_key'] ?? '');
        $this->setHeaderValue($values, '订单号', $row['order_id'] ?? '');
        $this->setFirstHeaderValue($values, ['款图', '主图', '款式图', '产品图', '订单图片'], $row['product_image'] ?? '');
        $this->setFirstHeaderValue($values, ['款式', '产品类型'], $row['style'] ?? '');
        $this->setFirstHeaderValue($values, ['衣服颜色', '裤子颜色'], $row['color'] ?? '');
        $this->setHeaderValue($values, '尺码', $row['size'] ?? '');
        $this->setHeaderValue($values, '数量', $row['quantity'] ?? '');
        $this->setHeaderValue($values, 'sku', $row['sku'] ?? '');
        $this->setHeaderValue($values, 'cleaned_sku', $row['cleaned_sku'] ?? '');
        $this->setHeaderValue($values, '产品规格', $row['product_specs'] ?? '');
        $this->setHeaderValue($values, '产品链接', $row['sales_link'] ?? '');

        return $values;
    }

    protected function withProductSpecsHeader(array $headers)
    {
        foreach (['产品规格', 'sku', 'cleaned_sku', '产品链接'] as $header) {
            if (!in_array($header, $headers, true)) {
                $headers[] = $header;
            }
        }

        return $headers;
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        return $values;
    }

    protected function applyCommonOptionRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $previousAttribute = null;
        $hasSleeveOption = $this->hasSleeveOption($attributes);
        $hasBodyPositionOption = false;
        $defaultLeftSleeveNameLines = [];

        $this->setGarmentColorFromFirstThreeAttributes(
            $values,
            $attributes,
            $context['color_lookup'] ?? [],
            $context['color_translation_resolver'] ?? null
        );

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($name === '' || $value === '') {
                $previousAttribute = $attribute;
                continue;
            }

            if ($this->isBodyPositionOptionName($lowerName)) {
                $this->setFirstHeaderValueIfBlank($values, $this->positionHeaders(), $this->mapEmbroideryPosition($value));
                $hasBodyPositionOption = true;
                $previousAttribute = $attribute;
                continue;
            }

            if (strpos($lowerName, 'nickname') !== false && !$this->shouldSkipNicknameOption($lowerName, $value)) {
                $this->appendFirstHeaderValue($values, ['胸口信息文本', '胸口信息', '胸部信息'], $value);
                $previousAttribute = $attribute;
                continue;
            }

            if ($name === 'Custom Chest Title') {
                $this->setFirstHeaderValueIfBlank($values, ['胸口信息文本', '胸口信息', '胸部信息'], $value);
                $previousAttribute = $attribute;
                continue;
            }

            if (strpos($lowerName, 'greeting card') !== false || strpos($lowerName, 'gift bag') !== false) {
                $this->appendGiftDisplayValues($values, $row, $context, $name, $value, $lowerName);
                $previousAttribute = $attribute;
                continue;
            }

            $lineNumber = $this->extractNameLineNumber($name);
            if (!$hasSleeveOption && $lineNumber !== null) {
                $defaultLeftSleeveNameLines[] = $this->formatNameLineValue($name, $value, $lineNumber);
            }

            if ((strpos($lowerName, 'name') !== false || strpos($lowerName, 'text') !== false)
                && !$this->isIgnoredPlaceholderValue($value)) {
                if (strpos($lowerName, 'left sleeve') !== false) {
                    $this->setFirstHeaderValueIfBlank($values, ['左袖信息', '左袖文本'], $value);
                } elseif (strpos($lowerName, 'right sleeve') !== false) {
                    $this->setFirstHeaderValueIfBlank($values, ['右袖信息', '右袖文本'], $value);
                } elseif (strpos($lowerName, 'collar') !== false || strpos($lowerName, 'neckline')) {
                    $this->setFirstHeaderValueIfBlank($values, ['领口信息'], $value);
                }
            }

            if ($this->isIconOrPatternOption($lowerName)) {
                $target = $this->sleeveTargetFromOptionName($lowerName);

                if ($target === '' && $previousAttribute !== null) {
                    $target = $this->sleeveTargetFromOptionName(strtolower((string) ($previousAttribute['name'] ?? '')));
                }

                if ($target !== '') {
                    foreach ($this->displayValuesFromOptionValues($row, $context, $name, $value, true) as $displayValue) {
                        if ($target === 'left') {
                            $this->appendFirstHeaderValue($values, ['左袖图标', '左袖符号'], $this->formatIconValue($name, $displayValue, $lineNumber));
                        } elseif ($target === 'right') {
                            $this->appendFirstHeaderValue($values, ['右袖图标', '右袖符号'], $this->formatIconValue($name, $displayValue, $lineNumber));
                        }
                    }
                }
            }

            if (strpos($lowerName, 'color') !== false && !$this->shouldSkipCommonColorRule($lowerName)) {
                if (strpos($lowerName, 'left sleeve') !== false) {
                    $translatedColor = $this->translateOptionColorValue(
                        $value,
                        $context['color_lookup'] ?? [],
                        $context['color_translation_resolver'] ?? null
                    );
                    $this->setFirstHeaderValueIfBlank($values, ['左袖线色', '左袖绣线颜色'], $translatedColor);
                } elseif (strpos($lowerName, 'right sleeve') !== false) {
                    $translatedColor = $this->translateOptionColorValue(
                        $value,
                        $context['color_lookup'] ?? [],
                        $context['color_translation_resolver'] ?? null
                    );
                    $this->setFirstHeaderValueIfBlank($values, ['右袖线色', '右袖绣线颜色'], $translatedColor);
                } elseif (strpos($lowerName, 'collar') !== false) {
                    $translatedColor = $this->translateOptionColorValue(
                        $value,
                        $context['color_lookup'] ?? [],
                        $context['color_translation_resolver'] ?? null
                    );
                    $this->setFirstHeaderValueIfBlank($values, ['领口文本颜色'], $translatedColor);
                } elseif (strpos($lowerName, 'sleeve') !== false || strpos($lowerName, 'thread') !== false) {
                    $translatedColor = $this->translateOptionColorValue(
                        $value,
                        $context['color_lookup'] ?? [],
                        $context['color_translation_resolver'] ?? null
                    );
                    $this->setFirstHeaderValueIfBlank($values, ['袖子线色', '袖子绣线颜色'], $translatedColor);
                } elseif (strpos($lowerName, 'chest') !== false) {
                    $translatedColor = $this->translateOptionColorValue(
                        $value,
                        $context['color_lookup'] ?? [],
                        $context['color_translation_resolver'] ?? null
                    );
                    $this->setFirstHeaderValueIfBlank($values, ['胸口信息颜色', '胸部信息颜色', '胸口文本颜色', '胸部文本颜色'], $translatedColor);
                }
            }

            $previousAttribute = $attribute;
        }

        if (!$hasSleeveOption && !empty($defaultLeftSleeveNameLines)) {
            $this->setFirstHeaderValueIfBlank($values, ['左袖信息', '左袖文本'], implode("\n", $defaultLeftSleeveNameLines));
        }

        if (!$hasBodyPositionOption) {
            $this->applyFixedSkuPlacementFallback($values, $row, $context);
        }

        return $values;
    }

    protected function shouldSkipCommonColorRule($lowerName)
    {
        return false;
    }

    protected function shouldSkipNicknameOption($lowerName, $value)
    {
        return false;
    }

    protected function attributesAfter($specs, $skipCount)
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

    protected function firstAttributeValue(array $attributes, array $needles)
    {
        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);
            $matches = true;

            foreach ($needles as $needle) {
                if (strpos($name, strtolower($needle)) === false) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $attribute['value'];
            }
        }

        return '';
    }

    protected function setHeaderValue(array &$values, $header, $value)
    {
        $index = $this->headerIndex($header);

        if ($index !== null) {
            $values[$index] = $value;
        }
    }

    protected function setHeaderValueIfBlank(array &$values, $header, $value)
    {
        $index = $this->headerIndex($header);

        if ($index !== null && ($values[$index] ?? '') === '') {
            $values[$index] = $value;
        }
    }

    protected function setFirstHeaderValue(array &$values, array $headers, $value)
    {
        foreach ($headers as $header) {
            $index = $this->headerIndex($header);

            if ($index !== null) {
                $values[$index] = $value;
                return;
            }
        }
    }

    protected function setFirstHeaderValueIfBlank(array &$values, array $headers, $value)
    {
        foreach ($headers as $header) {
            $index = $this->headerIndex($header);

            if ($index !== null && ($values[$index] ?? '') === '') {
                $values[$index] = $value;
                return;
            }
        }
    }

    protected function appendFirstHeaderValue(array &$values, array $headers, $value)
    {
        foreach ($headers as $header) {
            $index = $this->headerIndex($header);

            if ($index === null) {
                continue;
            }

            if (($values[$index] ?? '') === '') {
                $values[$index] = $value;
            } else {
                $values[$index] .= "\n" . $value;
            }

            return;
        }
    }

    private function applySleevePositionColumnRules(array &$values)
    {
        if ($this->hasAnyHeaderValue($values, ['左袖信息', '左袖文本', '左袖图标', '左袖符号'])) {
            $this->setSleevePositionAfterAnchor(
                $values,
                ['左袖图标', '左袖符号', '左袖信息', '左袖文本'],
                '左袖'
            );
        }

        if ($this->hasAnyHeaderValue($values, ['右袖信息', '右袖文本', '右袖图标', '右袖符号'])) {
            $this->setSleevePositionAfterAnchor(
                $values,
                ['右袖图标', '右袖符号', '右袖信息', '右袖文本'],
                '右袖'
            );
        }
    }

    private function setSleevePositionAfterAnchor(array &$values, array $anchorHeaders, $position)
    {
        $anchorIndex = $this->firstExistingHeaderIndex($anchorHeaders);

        if ($anchorIndex === null) {
            return;
        }

        $positionIndex = $this->firstSleevePositionHeaderIndexAfter($anchorIndex);

        if ($positionIndex !== null) {
            $values[$positionIndex] = $position;
        }
    }

    private function hasAnyHeaderValue(array $values, array $headers)
    {
        foreach ($headers as $header) {
            $index = $this->headerIndex($header);

            if ($index !== null && ($values[$index] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function firstExistingHeaderIndex(array $headers)
    {
        foreach ($headers as $header) {
            $index = $this->headerIndex($header);

            if ($index !== null) {
                return $index;
            }
        }

        return null;
    }

    private function firstSleevePositionHeaderIndexAfter($anchorIndex)
    {
        foreach ($this->headers() as $index => $header) {
            if ($index <= $anchorIndex) {
                continue;
            }

            if (strpos((string) $header, '袖子位置') !== false) {
                return $index;
            }
        }

        return null;
    }

    protected function headerIndex($header)
    {
        $headers = $this->headers();

        foreach ($headers as $index => $currentHeader) {
            if ($currentHeader === $header) {
                return $index;
            }
        }

        return null;
    }

    protected function translateLookupValue($value, array $lookup, $fallbackTranslator = null)
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

        if ($fallbackTranslator !== null && method_exists($fallbackTranslator, 'translate')) {
            return $fallbackTranslator->translate($value);
        }

        return $value;
    }

    protected function resolveOptionImage(array $context, array $row, $optionName, $optionValue)
    {
        $resolver = $context['sku_option_image_resolver'] ?? null;

        if ($resolver === null || !method_exists($resolver, 'resolve')) {
            return '';
        }

        return (string) $resolver->resolve(
            $row['cleaned_sku'] ?? $row['sku'] ?? '',
            $optionName,
            $optionValue
        );
    }

    protected function resolveSkuPlacement(array $context, array $row)
    {
        $resolver = $context['sku_placement_resolver'] ?? null;

        if ($resolver === null || !method_exists($resolver, 'resolve')) {
            return '';
        }

        return (string) $resolver->resolve(
            $row['cleaned_sku'] ?? $row['sku'] ?? '',
            $row['website'] ?? ''
        );
    }

    protected function hasBodyPositionAttribute(array $attributes)
    {
        foreach ($attributes as $attribute) {
            if ($this->isBodyPositionOptionName(strtolower((string) ($attribute['name'] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    protected function isBodyPositionOptionName($lowerName)
    {
        $lowerName = strtolower((string) $lowerName);

        if (strpos($lowerName, 'sleeve') !== false || strpos($lowerName, 'collar') !== false || strpos($lowerName, 'neckline') !== false) {
            return false;
        }

        return strpos($lowerName, 'position') !== false
            || strpos($lowerName, 'placement') !== false;
    }

    protected function formatSpecAttributeLine(array $attribute)
    {
        return trim((string) ($attribute['name'] ?? '')) . '：' . trim((string) ($attribute['value'] ?? ''));
    }

    protected function translateOptionColorValue($value, array $lookup, $fallbackTranslator = null)
    {
        $parts = preg_split('/[,，]/', (string) $value);
        $translatedParts = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $translatedParts[] = $this->translateLookupValue($part, $lookup, $fallbackTranslator);
        }

        return implode(', ', $translatedParts);
    }

    private function applyFixedSkuPlacementFallback(array &$values, array $row, array $context)
    {
        $position = $this->resolveSkuPlacement($context, $row);

        if ($position === '') {
            return;
        }

        $this->setFirstHeaderValueIfBlank($values, $this->positionHeaders(), $position);
    }

    private function positionHeaders()
    {
        return ['胸口位置', '胸部位置', '烫画位置', '刺绣位置'];
    }

    private function setGarmentColorFromFirstThreeAttributes(array &$values, array $attributes, array $colorLookup, $fallbackTranslator = null)
    {
        foreach (array_slice($attributes, 0, 3) as $attribute) {
            $name = strtolower((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === '' || strpos($name, 'color') === false) {
                continue;
            }

            $this->setFirstHeaderValue($values, ['衣服颜色', '裤子颜色'], $this->translateOptionColorValue($value, $colorLookup, $fallbackTranslator));
            return;
        }
    }

    private function hasSleeveOption(array $attributes)
    {
        foreach ($attributes as $attribute) {
            $name = strtolower((string) ($attribute['name'] ?? ''));

            if (strpos($name, 'left sleeve') !== false || strpos($name, 'right sleeve') !== false) {
                return true;
            }
        }

        return false;
    }

    private function isIconOrPatternOption($lowerName)
    {
        return strpos($lowerName, 'icon') !== false || strpos($lowerName, 'pattern') !== false;
    }

    private function displayValuesFromOptionValues(array $row, array $context, $optionName, $optionValue, $filterIconPlaceholders, $filterPlaceholderValues = false)
    {
        $displayValues = [];

        foreach ($this->splitOptionValues($optionValue) as $part) {
            if (($filterIconPlaceholders && $this->isIgnoredIconValue($part))
                || ($filterPlaceholderValues && $this->isIgnoredPlaceholderValue($part))) {
                continue;
            }

            $image = $this->resolveOptionImage($context, $row, $optionName, $part);
            $displayValue = $image !== '' ? $image : $part;

            if (($filterIconPlaceholders && $this->isIgnoredIconValue($displayValue))
                || ($filterPlaceholderValues && $this->isIgnoredPlaceholderValue($displayValue))) {
                continue;
            }

            $displayValues[] = $displayValue;
        }

        return $displayValues;
    }

    private function splitOptionValues($optionValue)
    {
        $parts = preg_split('/[,，]/', (string) $optionValue);
        $values = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $values[] = $part;
            }
        }

        return $values;
    }

    private function isIgnoredIconValue($value)
    {
        $lowerValue = strtolower((string) $value);

        if ($this->isIgnoredPlaceholderValue($value)) {
            return true;
        }

        if (strpos($lowerValue, 'upload') !== false && strpos($lowerValue, 'photo') !== false) {
            return true;
        }

        if (strpos($lowerValue, 'upload') !== false && strpos($lowerValue, 'icon') !== false) {
            return true;
        }

        if (strpos($lowerValue, 'add') !== false
            && (strpos($lowerValue, 'name') !== false || strpos($lowerValue, 'text') !== false)) {
            return true;
        }

        if (strpos($lowerValue, 'choose') !== false && strpos($lowerValue, 'logo') !== false) {
            return true;
        }

        return strpos($lowerValue, 'no thank') !== false
            || strpos($lowerValue, 'no-thank') !== false
            || strpos($lowerValue, 'no_thank') !== false
            || strpos($lowerValue, 'no. thank') !== false;
    }

    private function appendGiftDisplayValues(array &$values, array $row, array $context, $optionName, $optionValue, $lowerName)
    {
        if ($this->isIgnoredGiftValue($optionValue)) {
            return;
        }

        $headers = ['贺卡/礼品', '贺卡/包装'];

        if (strpos($lowerName, 'greeting card') !== false) {
            $headers[] = '贺卡';
        }

        if (strpos($lowerName, 'gift bag') !== false) {
            $headers[] = '礼品袋';
        }

        foreach ($this->displayValuesFromOptionValues($row, $context, $optionName, $optionValue, false, true) as $displayValue) {
            if ($this->isIgnoredGiftValue($displayValue)) {
                continue;
            }

            $this->appendFirstHeaderValue($values, $headers, $displayValue);
        }
    }

    private function isIgnoredGiftValue($value)
    {
        return $this->isIgnoredPlaceholderValue($value);
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

    private function sleeveTargetFromOptionName($lowerName)
    {
        if (strpos($lowerName, 'left sleeve') !== false) {
            return 'left';
        }

        if (strpos($lowerName, 'right sleeve') !== false) {
            return 'right';
        }

        return '';
    }

    protected function applyNameLinesToSleeveInfo(array &$values, array $attributes, $target = 'left', $countOptionName = 'add names on the sleeve')
    {
        $count = $this->positiveIntegerAttributeValue($attributes, $countOptionName);

        if ($count <= 0) {
            return false;
        }

        $names = $this->collectNameLineValues($attributes);
        $selectedNames = [];

        foreach ($names as $lineNumber => $value) {
            if ($lineNumber <= $count) {
                $selectedNames[] = $value;
            }
        }

        if (empty($selectedNames)) {
            return false;
        }

        if ($target === 'right') {
            $this->setFirstHeaderValue($values, ['右袖信息', '右袖文本'], implode("\n", $selectedNames));
        } else {
            $this->setFirstHeaderValue($values, ['左袖信息', '左袖文本'], implode("\n", $selectedNames));
        }

        return true;
    }

    protected function appendNameLinesToChestInfo(array &$values, array $attributes, $clearMatchingLeftSleeve = true)
    {
        $nameLines = array_values($this->collectNameLineValues($attributes, true, true));

        if (empty($nameLines)) {
            return false;
        }

        $nameBlock = implode("\n", $nameLines);
        $this->appendFirstHeaderValue($values, ['胸口信息文本', '胸口信息', '胸部信息'], $nameBlock);

        if ($clearMatchingLeftSleeve) {
            $leftSleeveIndex = $this->headerIndex('左袖信息');

            if ($leftSleeveIndex !== null && ($values[$leftSleeveIndex] ?? '') === $nameBlock) {
                $values[$leftSleeveIndex] = '';
            }
        }

        return true;
    }

    protected function setNicknameNameLinesToChestInfo(array &$values, array $attributes, $label)
    {
        $lines = [];
        $nickname = $this->firstAttributeValue($attributes, ['nickname']);

        if ($nickname !== '') {
            $lines[] = $nickname;
        }

        $nameValues = array_values($this->collectNameLineValues($attributes, false, true));

        if (!empty($nameValues)) {
            $lines[] = $label;
            $lines = array_merge($lines, $nameValues);
        }

        if (empty($lines)) {
            return false;
        }

        $this->setFirstHeaderValue($values, ['胸口信息文本', '胸口信息', '胸部信息'], implode("\n", $lines));

        return true;
    }

    protected function collectNameLineValues(array $attributes, $includeOptionName = false, $excludeIconPatternSleeve = false)
    {
        $values = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);
            $lineNumber = $this->extractNameLineNumber($name);

            if ($lineNumber === null || $value === '') {
                continue;
            }

            if ($excludeIconPatternSleeve
                && (strpos($lowerName, 'icon') !== false
                    || strpos($lowerName, 'pattern') !== false
                    || strpos($lowerName, 'sleeve') !== false)) {
                continue;
            }

            $values[$lineNumber] = $includeOptionName ? $this->formatNameLineValue($name, $value, $lineNumber) : $value;
        }

        ksort($values);

        return $values;
    }

    private function positiveIntegerAttributeValue(array $attributes, $optionNameNeedle)
    {
        $optionNameNeedle = strtolower((string) $optionNameNeedle);

        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));

            if (strpos($name, $optionNameNeedle) === false) {
                continue;
            }

            $value = trim((string) ($attribute['value'] ?? ''));

            if (preg_match('/\d+/', $value, $matches)) {
                return (int) $matches[0];
            }

            return 0;
        }

        return 0;
    }

    private function extractNameLineNumber($optionName)
    {
        if (preg_match('/\bname\s*#?\s*(\d+)\b/i', (string) $optionName, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function formatIconValue($optionName, $value, $lineNumber)
    {
        if ($lineNumber === null) {
            return $value;
        }

        return $this->formatNameLineValue($optionName, $value, $lineNumber);
    }

    private function formatNameLineValue($optionName, $value, $lineNumber)
    {
        return $optionName . '：' . $value;
    }

    private function lineLabel($lineNumber)
    {
        $labels = [
            1 => '第一行',
            2 => '第二行',
            3 => '第三行',
            4 => '第四行',
            5 => '第五行',
            6 => '第六行',
            7 => '第七行',
            8 => '第八行',
            9 => '第九行',
            10 => '第十行',
        ];

        return $labels[$lineNumber] ?? ('第' . $lineNumber . '行');
    }

    protected function mapEmbroideryPosition($value)
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

        if (strpos($lowerValue, 'back') !== false || strpos($lowerValue, 'rear') !== false) {
            return '背部中央';
        }

        return $value;
    }
}
