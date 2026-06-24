<?php

namespace App\Services\OrderExportTemplates;

class TextEmbroideryTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'text_embroidery';
    }

    public function label()
    {
        return '文字款刺绣';
    }

    public function supportedChineseNames()
    {
        return ['文字款刺绣'];
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
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '袖子位置',
            '袖子绣线颜色',
            '备注',
            '胸口样式',
            '胸口信息',
            '胸口文本颜色',
            '胸部文本外框颜色',
            'This 字体',
            '胸口位置',
            '领口文本',
            '领口文本颜色',
            '领口字体',
            '领口位置',
            '贺卡/礼品',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $allAttributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $text = $this->firstAttributeValue($attributes, ['text']);
        $threadColor = $this->firstAttributeValue($attributes, ['thread color']);
        $printColor = $this->firstAttributeValue($attributes, ['print color']);
        $position = $this->firstAttributeValue($attributes, ['position']);
        $colorLookup = $context['color_lookup'] ?? [];
        $colorTranslator = $context['color_translation_resolver'] ?? null;

        if ($text !== '') {
            $this->setHeaderValue($values, '胸口信息', $text);
        }

        if ($threadColor !== '') {
            $this->setHeaderValue($values, '胸口文本颜色', $this->translateLookupValue($threadColor, $colorLookup, $colorTranslator));
        }

        if ($printColor !== '') {
            $this->setHeaderValue($values, '袖子绣线颜色', $this->translateLookupValue($printColor, $colorLookup, $colorTranslator));
        }

        if ($position !== '') {
            $this->setFirstHeaderValue($values, ['胸口位置', '领口位置'], $this->mapEmbroideryPosition($position));
        }

        if ($this->isSku($row, 'CS-CX-QK2584')) {
            $this->applyCxQk2584Rules($values, $allAttributes, $colorLookup, $colorTranslator);
        }

        if ($this->isSku($row, 'CS-QK5322-CX')) {
            $this->applyQk5322Rules($values, $allAttributes);
        }

        if ($this->isSku($row, 'CS-QK5874-CX')) {
            $this->applyQk5874Rules($values, $allAttributes);
        }

        if ($this->isQk5914($row)) {
            $this->applyQk5914Rules($values, $allAttributes);
        }

        return $values;
    }

    private function applyCxQk2584Rules(array &$values, array $attributes, array $colorLookup, $colorTranslator)
    {
        $nickname = $this->firstExactAttributeValue($attributes, 'Enter Nickname');
        $middleText = $this->firstExactAttributeValue($attributes, 'Customize The Cursive Text In The Middle Of Your Nickname');
        $middleTextColor = $this->firstExactAttributeValue($attributes, 'Choose Thread Color');
        $chestLines = [];
        $colorLines = [];

        if ($nickname !== '') {
            $chestLines[] = $nickname;
            $colorLines[] = $nickname . '：' . $this->nicknameColorForGarment($values);
        }

        if ($middleText !== '') {
            $chestLines[] = '中间文本：' . $middleText;
        }

        if ($middleTextColor !== '') {
            $colorLines[] = '中间文本：' . $this->translateLookupValue($middleTextColor, $colorLookup, $colorTranslator);
        }

        if (!empty($chestLines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $chestLines));
        }

        if (!empty($colorLines)) {
            $this->setHeaderValue($values, '胸口文本颜色', implode("\n", $colorLines));
        }
    }

    private function applyQk5322Rules(array &$values, array $attributes)
    {
        $lines = [];
        $nickname = $this->firstExactAttributeValue($attributes, 'Nickname');

        if ($nickname !== '') {
            $lines[] = $nickname;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value !== '' && strpos($name, 'year') !== false) {
                $lines[] = 'EST. ' . $this->formatEstYearPart($value);
            }
        }

        if (!empty($lines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $lines));
        }
    }

    private function applyQk5874Rules(array &$values, array $attributes)
    {
        $lines = [];
        $title = $this->firstExactAttributeValue($attributes, 'Custom Your Title');
        $textUnderTitle = $this->firstExactAttributeValue($attributes, 'Text Under The Title');

        if ($title !== '') {
            $lines[] = '左上带心的字：' . $title;
        }

        if ($textUnderTitle !== '') {
            $lines[] = '右下字：' . $textUnderTitle;
        }

        if (!empty($lines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $lines));
        }
    }

    private function applyQk5914Rules(array &$values, array $attributes)
    {
        $lines = [];
        $nickname = $this->firstAttributeValue($attributes, ['nickname']);

        if ($nickname !== '') {
            $lines[] = $nickname;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            if (strpos($name, 'est') !== false || strpos($name, 'year') !== false) {
                $lines[] = $value;
            }
        }

        if (!empty($lines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $lines));
        }
    }

    private function isQk5914(array $row)
    {
        return $this->isSku($row, 'CS-QK5914-CX');
    }

    private function isSku(array $row, $targetSku)
    {
        $targetSku = strtoupper(trim((string) $targetSku));
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === $targetSku || strpos($sku, $targetSku) !== false;
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

    private function nicknameColorForGarment(array $values)
    {
        $garmentColor = strtolower((string) ($values[$this->headerIndex('衣服颜色')] ?? ''));

        if (strpos($garmentColor, '白色') !== false
            || strpos($garmentColor, '灰色') !== false
            || strpos($garmentColor, '军绿色') !== false
            || strpos($garmentColor, 'white') !== false
            || strpos($garmentColor, 'gray') !== false
            || strpos($garmentColor, 'grey') !== false
            || strpos($garmentColor, 'army green') !== false
            || strpos($garmentColor, 'military green') !== false) {
            return '黑色';
        }

        return '白色';
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
