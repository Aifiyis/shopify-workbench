<?php

namespace App\Services\OrderExportTemplates;

class PersonOutlineColorTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'person_outline_color';
    }

    public function label()
    {
        return '人物轮廓彩图';
    }

    public function supportedChineseNames()
    {
        return ['人物轮廓', '人物彩图', '人物轮廓彩图'];
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
            '备注',
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '袖子位置',
            '袖子绣线颜色',
            '胸口样式',
            '胸口信息',
            '胸部文本颜色',
            '图片轮廓线色',
            '胸部位置',
            '后背位置',
            '全彩/轮廓',
            '胸口文本闪片颜色',
            '刺绣风格',
            '音乐播放器歌曲名字',
            '音乐播放器歌曲作者',
            '内衣裤颜色',
            '内衣裤全彩/轮廓',
            '身体轮廓颜色',
            '大腿位置',
            '是否给图片添加边框',
            '图片1',
            '图片1下方文字',
            '图片2',
            '图片3',
            '图片4',
            '图片5',
            '图片6',
            '备注',
            '贺卡',
            '礼品袋',
            '备注',
            '设计稿',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $allAttributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $this->setHeaderValue($values, '图片1', $photo);
        }

        if (($row['chinese_name'] ?? '') === '人物轮廓') {
            $this->setHeaderValue($values, '全彩/轮廓', '轮廓');
        }

        if (($row['chinese_name'] ?? '') === '人物彩图') {
            $this->setHeaderValue($values, '全彩/轮廓', '全彩');
        }

        if (!$this->hasBodyPositionAttribute($allAttributes)) {
            $this->setHeaderValueIfBlank($values, '胸部位置', '胸部中央');
        }

        $this->applyPhotoUploadRules($values, $allAttributes);
        $this->applyImageCaptionRules($values, $allAttributes, $row);
        $this->applySleeveNameRules($values, $allAttributes);
        $this->applyHr2938Rules($values, $allAttributes, $row, $context);
        $this->applyHr2572Rules($values, $allAttributes, $row, $context);
        $this->applyQk0976Rules($values, $allAttributes, $row);
        $this->applyOutlineThreadColorRules($values, $allAttributes, $context);
        $this->applyFullColorCenterThreadColorRules($values, $allAttributes, $context);

        return $values;
    }

    private function applyHr2938Rules(array &$values, array $attributes, array $row, array $context)
    {
        if (!$this->isHr2938($row)) {
            return;
        }

        $chestLines = [];
        $chooseTitle = $this->firstExactAttributeValue($attributes, 'Choose Your Title');
        $enterTitle = $this->firstExactAttributeValue($attributes, 'Enter Your Title');
        $title = '';

        if ($chooseTitle !== '' && stripos($chooseTitle, 'custom your title') === false) {
            $title = $chooseTitle;
        } elseif ($enterTitle !== '') {
            $title = $enterTitle;
        }

        if ($title !== '') {
            $chestLines[] = $title;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            if (strpos($name, 'est') !== false || strpos($name, 'year') !== false) {
                $chestLines[] = 'EST. ' . $this->formatEstYearPart($value);
            }
        }

        if (!empty($chestLines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $chestLines));
        }

        $pattern = $this->firstExactAttributeValue($attributes, 'Choose Pattern under the names');

        if ($pattern !== '' && !$this->isIgnoredPlaceholderValue($pattern)) {
            $image = $this->resolveOptionImage($context, $row, 'Choose Pattern under the names', $pattern);
            $this->setHeaderValue($values, '左袖图标', $image !== '' ? $image : $pattern);
        }

        $names = $this->collectNameLineValues($attributes);

        if (!empty($names)) {
            ksort($names);
            $this->setHeaderValue($values, '左袖信息', implode("\n", array_values($names)));
        }
    }

    private function isHr2938(array $row)
    {
        return $this->isSku($row, 'CS-HR2938-CX');
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

    private function applyOutlineThreadColorRules(array &$values, array $attributes, array $context)
    {
        if (($values[$this->headerIndex('全彩/轮廓')] ?? '') !== '轮廓') {
            return;
        }

        $threadColor = $this->firstExactAttributeValue($attributes, 'Thread Color');
        $outlineColor = $threadColor !== '' ? $threadColor : $this->firstExactAttributeValue($attributes, 'Choose Thread Color');

        if ($outlineColor === '') {
            return;
        }

        $translatedColor = $this->translateLookupValue(
            $outlineColor,
            $context['color_lookup'] ?? [],
            $context['color_translation_resolver'] ?? null
        );
        $this->setFirstHeaderValue($values, ['图片轮廓线色', '图片轮廓颜色'], $translatedColor);

        if ($threadColor === '') {
            return;
        }

        if ($this->hasAnyHeaderValue($values, ['左袖信息', '左袖图标', '右袖信息', '右袖图标'])) {
            $this->setHeaderValue($values, '袖子绣线颜色', $translatedColor);
        } else {
            $this->setHeaderValue($values, '袖子绣线颜色', '');
        }
    }

    private function applyFullColorCenterThreadColorRules(array &$values, array $attributes, array $context)
    {
        if (($values[$this->headerIndex('全彩/轮廓')] ?? '') !== '全彩'
            || ($values[$this->headerIndex('胸部位置')] ?? '') !== '胸部中央') {
            return;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === ''
                || strpos($name, 'thread color') === false
                || strpos($name, 'sleeve') !== false) {
                continue;
            }

            $this->setHeaderValue($values, '图片轮廓线色', $this->translateLookupValue(
                $value,
                $context['color_lookup'] ?? [],
                $context['color_translation_resolver'] ?? null
            ));
            return;
        }
    }

    protected function shouldSkipNicknameOption($lowerName, $value)
    {
        return strpos(strtolower((string) $value), 'custom your own') !== false
            || strpos($lowerName, 'custom text unter the nickname') !== false;
    }

    private function applyPhotoUploadRules(array &$values, array $attributes)
    {
        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === '' || !preg_match('/^upload your photo(?:[_\s]+(\d+))?$/i', $name, $matches)) {
                continue;
            }

            $photoNumber = isset($matches[1]) ? (int) $matches[1] : 1;
            if ($photoNumber < 1 || $photoNumber > 6) {
                continue;
            }

            $this->appendUniqueHeaderValue($values, '图片' . $photoNumber, $value);
        }
    }

    private function applyHr2572Rules(array &$values, array $attributes, array $row, array $context)
    {
        if (!$this->isSku($row, 'CS-HR2572-CX')) {
            return;
        }

        $chestLines = [];
        $nickname = $this->firstExactAttributeValue($attributes, 'Enter Nickname');
        $lovingMessage = $this->firstExactAttributeValue($attributes, 'Custom a Loving Message Below the Photo');

        if ($nickname !== '') {
            $chestLines[] = $nickname;
        }

        if ($lovingMessage !== '') {
            $chestLines[] = $lovingMessage;
        }

        if (!empty($chestLines)) {
            $this->setHeaderValue($values, '胸口信息', implode("\n", $chestLines));
        }

        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === ''
                || strpos($name, 'on the left sleeve') === false
                || strpos($name, 'name') === false
                || strpos($name, 'add') !== false) {
                continue;
            }

            $this->appendUniqueHeaderValue($values, '左袖信息', $value);
        }

        $font = $this->firstExactAttributeValue($attributes, 'Choose Font For Nickname');

        if ($font !== '') {
            $image = $this->resolveOptionImage($context, $row, 'Choose Font For Nickname', $font);
            $this->setHeaderValue($values, '胸口样式', $image !== '' ? $image : $font);
        }
    }

    private function applyQk0976Rules(array &$values, array $attributes, array $row)
    {
        if (!$this->isSku($row, 'CS-QK0976-CX')) {
            return;
        }

        $customText = $this->firstExactAttributeValue($attributes, 'Custom text or roman numerals');

        if ($customText !== '') {
            $this->setHeaderValue($values, '图片1下方文字', $customText);
        }

        $border = strtolower($this->firstExactAttributeValue($attributes, 'Add A Border To The Image'));

        if ($border === 'yes') {
            $this->setHeaderValue($values, '是否给图片添加边框', '是');
        }
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

    private function appendUniqueHeaderValue(array &$values, $header, $value)
    {
        $index = $this->headerIndex($header);

        if ($index === null) {
            return;
        }

        $existingValue = (string) ($values[$index] ?? '');
        $existingParts = $existingValue === '' ? [] : preg_split('/\r\n|\n|\r/', $existingValue);

        if (in_array($value, $existingParts, true)) {
            return;
        }

        if ($existingValue === '') {
            $values[$index] = $value;
        } else {
            $values[$index] .= "\n" . $value;
        }
    }

    private function applyImageCaptionRules(array &$values, array $attributes, array $row)
    {
        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($value === '') {
                continue;
            }

            if (strpos($lowerName, 'custom text unter the nickname') !== false
                || (!$this->isHr2938($row) && (strpos($lowerName, 'est') !== false || strpos($lowerName, 'year') !== false))) {
                $this->appendFirstHeaderValue($values, ['图片1下方文字'], $value);
            }
        }
    }

    private function applySleeveNameRules(array &$values, array $attributes)
    {
        $this->applyNameLinesToSleeveInfo($values, $attributes, 'left', 'add names on sleeve');
    }

    private function isSku(array $row, $targetSku)
    {
        $targetSku = strtoupper(trim((string) $targetSku));
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === $targetSku || strpos($sku, $targetSku) !== false;
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
}
