<?php

namespace App\Services\OrderExportTemplates;

class HeatTransferClothingTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'heat_transfer_clothing';
    }

    public function label()
    {
        return '普通烫画衣服';
    }

    public function supportedChineseNames()
    {
        return ['普通烫画卫衣', '普通烫画衣服'];
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
            '左袖线色',
            '备注',
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '右袖线色',
            '右袖闪片颜色',
            '袖子位置',
            '胸口样式',
            '胸口信息',
            '胸口信息颜色',
            '胸口信息闪片颜色',
            '备注',
            '设计稿',
            '胸口妈妈肤色',
            '胸口妈妈发色',
            '汽车风格1',
            '汽车风格2',
            '汽车1颜色',
            '汽车2颜色',
            '全彩/轮廓',
            '胸口图片背景色',
            '照片艺术风格（黑白复古/原图）',
            '胸口图片1',
            '胸口图片1下方文字',
            '胸口图片2',
            '胸口图片3',
            '胸口图片4',
            '胸口图片5',
            '胸口图片6',
            '备注',
            '肤色',
            '后背风格',
            '后背T恤闪片颜色',
            '后背裤子闪片颜色',
            '后背头盔闪片颜色',
            '后背信息',
            '后背图片',
            '后背信息颜色',
            '备注',
            '烫画位置',
            '贺卡',
            '礼品袋',
            '备注',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $allAttributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $this->setHeaderValue($values, '设计稿', $photo);
        }

        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? $row['sku'] ?? '')));

        if ($cleanedSku === 'CS-MYX8437-TH') {
            return $this->applySeagullRules($values, $allAttributes, $context);
        }

        if ($cleanedSku === 'CS-JZF6799-TH') {
            return $this->applyRegimentRules($values, $allAttributes);
        }

        if ($cleanedSku === 'CS-QK3543-TH') {
            $this->applyQk3543Rules($values, $allAttributes);
        }

        if ($cleanedSku === 'CS-QK3311-TH') {
            $this->applyQk3311Rules($values, $allAttributes, $row, $context);
        }

        if ($cleanedSku === 'CS-MYX7637-TH') {
            $this->applyMyx7637Rules($values, $allAttributes);
        }

        $recipient = $this->firstExactAttributeValue($allAttributes, 'Choose Recipient');

        if ($recipient !== '') {
            $this->setHeaderValueIfBlank($values, '胸口信息', $recipient);
        }

        if ($cleanedSku !== 'CS-QK3311-TH'
            && !$this->hasBodyPositionAttribute($allAttributes)
            && $this->resolveSkuPlacement($context, $row) !== '') {
            $summary = $this->formatChestInfoFromCustomAttributes($attributes);

            if ($summary !== '') {
                $this->appendFirstHeaderValue($values, ['胸口信息'], $summary);
            }
        }

        if (!$this->hasBodyPositionAttribute($allAttributes)) {
            $this->setHeaderValueIfBlank($values, '烫画位置', '胸部中央');
        }

        $this->appendNameLinesToChestInfoWhenCentered($values, $allAttributes);
        $this->applyNicknameNameCountChestInfoRules($values, $allAttributes);

        return $values;
    }

    private function applySeagullRules(array $values, array $attributes, array $context)
    {
        $customText = $this->firstExactAttributeValue($attributes, 'Custom Text');
        $lines = [];

        if ($customText !== '') {
            $lines[] = $customText;
        }

        $this->setHeaderValue($values, '胸口信息', implode("\n", $lines));
        $this->setHeaderValue($values, '左袖信息', '');
        $this->setHeaderValueIfBlank($values, '烫画位置', '胸部中央');
        $this->appendNameLinesToChestInfoWhenCentered($values, $attributes);
        $this->applyNicknameNameCountChestInfoRules($values, $attributes);

        $textColor = $this->firstExactAttributeValue($attributes, 'Text Color');

        if ($textColor !== '') {
            $this->setHeaderValue($values, '胸口信息颜色', $this->translateOptionColorValue(
                $textColor,
                $context['color_lookup'] ?? [],
                $context['color_translation_resolver'] ?? null
            ));
        }

        return $values;
    }

    private function applyQk3543Rules(array &$values, array $attributes)
    {
        $image = $this->firstExactAttributeValue($attributes, 'Add Your Image');

        if ($image !== '') {
            $this->setHeaderValue($values, '胸口图片1', $image);
        }

        $this->setHeaderValue($values, '照片艺术风格（黑白复古/原图）', '黑白');
    }

    private function applyQk3311Rules(array &$values, array $attributes, array $row, array $context)
    {
        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($name === '' || $value === '' || $this->isIgnoredOptionValue($value)) {
                continue;
            }

            if (strcasecmp($name, 'Add Paws and Hands Line Art Design on Sleeve') === 0) {
                $imagePath = $this->resolveOptionImage($context, $row, $name, $value);
                $this->setHeaderValue($values, '左袖图标', $imagePath !== '' ? $imagePath : $value);
                continue;
            }

            if (strcasecmp($name, 'Enter Text On Left Sleeve') === 0) {
                $this->setHeaderValue($values, '左袖信息', $value);
                continue;
            }

            if (strpos($lowerName, 'pet name') !== false) {
                if (($values[$this->headerIndex('烫画位置')] ?? '') === '背部中央') {
                    $this->appendFirstHeaderValue($values, ['后背信息'], $value);
                } else {
                    $this->appendFirstHeaderValue($values, ['胸口信息'], $value);
                }
            }
        }
    }

    private function applyMyx7637Rules(array &$values, array $attributes)
    {
        $familyName = $this->firstExactAttributeValue($attributes, 'Family Name');
        $kidsName = $this->firstExactAttributeValue($attributes, 'Kids Name');

        if ($familyName !== '' && !$this->isIgnoredOptionValue($familyName)) {
            $this->setHeaderValue($values, '左袖信息', $familyName);
        }

        if ($kidsName !== '' && !$this->isIgnoredOptionValue($kidsName)) {
            $this->appendFirstHeaderValue($values, ['胸口信息'], '小孩名：' . $kidsName);
        }
    }

    private function applyRegimentRules(array $values, array $attributes)
    {
        $lines = [];
        $name = $this->firstExactAttributeValue($attributes, 'Custom Name');
        $regiment = $this->firstExactAttributeValue($attributes, 'Custom Regiment');
        $dates = $this->firstExactAttributeValue($attributes, 'Custom Dates');

        if ($name !== '') {
            $lines[] = '姓名：' . $name;
        }

        if ($regiment !== '') {
            $lines[] = '军团：' . $regiment;
        }

        if ($dates !== '') {
            $lines[] = '年份：' . $dates;
        }

        $this->setHeaderValue($values, '胸口信息', implode("\n", $lines));
        $this->setHeaderValueIfBlank($values, '烫画位置', '胸部中央');

        return $values;
    }

    private function formatChestInfoFromCustomAttributes(array $attributes)
    {
        $lines = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            if ($this->isBodyPositionOptionName(strtolower($name))) {
                continue;
            }

            $lines[] = $this->formatSpecAttributeLine($attribute);
        }

        return implode("\n", $lines);
    }

    private function firstExactAttributeValue(array $attributes, $targetName)
    {
        foreach ($attributes as $attribute) {
            if (strcasecmp(trim((string) ($attribute['name'] ?? '')), $targetName) === 0) {
                return trim((string) ($attribute['value'] ?? ''));
            }
        }

        return '';
    }

    private function appendNameLinesToChestInfoWhenCentered(array &$values, array $attributes)
    {
        if (($values[$this->headerIndex('烫画位置')] ?? '') !== '胸部中央') {
            return;
        }

        $nameLines = $this->nameLineValues($attributes);

        if (empty($nameLines)) {
            return;
        }

        $nameBlock = implode("\n", $nameLines);
        $this->appendFirstHeaderValue($values, ['胸口信息'], $nameBlock);

        $leftSleeveIndex = $this->headerIndex('左袖信息');
        if ($leftSleeveIndex !== null && ($values[$leftSleeveIndex] ?? '') === $nameBlock) {
            $values[$leftSleeveIndex] = '';
        }
    }

    private function nameLineValues(array $attributes)
    {
        $values = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($value === '') {
                continue;
            }

            if (strpos($lowerName, 'icon') !== false
                || strpos($lowerName, 'pattern') !== false
                || strpos($lowerName, 'sleeve') !== false) {
                continue;
            }

            if (preg_match('/\bname\s*#?\s*(\d+)\b/i', $name, $matches)) {
                $values[(int) $matches[1]] = $name . '：' . $value;
            }
        }

        ksort($values);

        return array_values($values);
    }

    private function applyNicknameNameCountChestInfoRules(array &$values, array $attributes)
    {
        $label = '';

        if ($this->hasAttributeNameContaining($attributes, 'number of seagulls')) {
            $label = '海鸥身上小孩名：';
        } elseif ($this->hasAttributeNameContaining($attributes, 'number of kids')
            || $this->hasAttributeNameContaining($attributes, 'number of children')) {
            $label = '小孩名：';
        }elseif ($this->hasAttributeNameContaining($attributes, 'number of Pet')){
            $label = '宠物名：';
        }
    

        if ($label === '') {
            return;
        }

        $lines = [];
        $nickname = $this->firstAttributeValue($attributes, ['nickname']);

        if ($nickname !== '') {
            $lines[] = $nickname;
        }

        $nameValues = $this->nameOnlyValues($attributes);

        if (!empty($nameValues)) {
            $lines[] = $label;
            $lines = array_merge($lines, $nameValues);
        }

        if (!empty($lines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $lines));
        }
    }

    private function hasAttributeNameContaining(array $attributes, $needle)
    {
        foreach ($attributes as $attribute) {
            if (strpos(strtolower((string) ($attribute['name'] ?? '')), $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function nameOnlyValues(array $attributes)
    {
        $values = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($value === '') {
                continue;
            }

            if (strpos($lowerName, 'icon') !== false
                || strpos($lowerName, 'pattern') !== false
                || strpos($lowerName, 'sleeve') !== false) {
                continue;
            }

            if (preg_match('/\bname\s*#?\s*(\d+)\b/i', $name, $matches)) {
                $values[(int) $matches[1]] = $value;
            }
        }

        ksort($values);

        return array_values($values);
    }

    private function isIgnoredOptionValue($value)
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
}
