<?php

namespace App\Services\OrderExportTemplates;

class PetOutlineColorTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'pet_outline_color';
    }

    public function label()
    {
        return '宠物轮廓彩图';
    }

    public function supportedChineseNames()
    {
        return ['宠物轮廓', '宠物彩图', '宠物轮廓彩图'];
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
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '右袖字体',
            '袖子位置',
            '袖子绣线颜色',
            '胸口样式',
            '宠物名字胸部信息',
            '年份（分布在图片左右两侧）',
            '胸部文本颜色',
            '名字文本外框颜色',
            '是否添加天使光环或翅膀',
            '文本样式',
            '胸口位置',
            '全彩/轮廓',
            '图片轮廓线色',
            '后背位置',
            '图片1',
            '图片1下方文字',
            '图片2',
            '图片3',
            '备注',
            '贺卡/包装',
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

        if ($this->hasAttributeNameContaining($allAttributes, 'number of pet')) {
            $this->applyNumberedPetPhotoRules($values, $allAttributes);
        }

        if (($row['chinese_name'] ?? '') === '宠物轮廓') {
            $this->setHeaderValue($values, '全彩/轮廓', '轮廓');
        }

        if (($row['chinese_name'] ?? '') === '宠物彩图') {
            $this->setHeaderValue($values, '全彩/轮廓', '全彩');
        }

        if ($this->isQk0833($row)) {
            $this->applyQk0833Rules(
                $values,
                $allAttributes,
                $row,
                $context,
                $context['color_lookup'] ?? [],
                $context['color_translation_resolver'] ?? null
            );
        }

        if ($this->isQk3961($row)) {
            $this->applyQk3961Rules($values, $allAttributes, $row, $context);
        }

        if ($this->isMyx6625($row)) {
            $this->applyMyx6625Rules($values, $allAttributes, $row, $context);
        }

        $this->applyOutlineThreadColorRules(
            $values,
            $allAttributes,
            $context['color_lookup'] ?? [],
            $context['color_translation_resolver'] ?? null
        );

        $this->setHeaderValueIfBlank($values, '胸口位置', '胸部中央');

        return $values;
    }

    private function applyNumberedPetPhotoRules(array &$values, array $attributes)
    {
        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($value === ''
                || strpos($lowerName, 'text below') !== false
                || strpos($lowerName, 'text under') !== false) {
                continue;
            }

            if (preg_match('/photo\s*#?\s*(\d+)/i', $name, $matches)) {
                $this->setHeaderValue($values, '图片' . (int) $matches[1], $value);
            }
        }
    }

    private function applyQk0833Rules(array &$values, array $attributes, array $row, array $context, array $colorLookup, $colorTranslator)
    {
        $petName = $this->firstExactAttributeValue($attributes, 'Pet Name');
        $est = $this->firstExactAttributeValue($attributes, 'EST');
        $outlineColor = $this->firstExactAttributeValue($attributes, 'Thread Color For Pet Name Outline');
        $nameColor = $this->firstExactAttributeValue($attributes, 'Thread Color For Pet Name');
        $estColor = $this->firstExactAttributeValue($attributes, 'Thread Color For EST and Other Text');
        $sleeveIcon = $this->firstExactAttributeValue($attributes, 'Choose Icon On Sleeve');

        if ($petName !== '') {
            $this->setHeaderValue($values, '宠物名字胸部信息', $petName);
        }

        if ($est !== '') {
            $this->setHeaderValue($values, '年份（分布在图片左右两侧）', $est);
        }

        $colorLines = [];
        if ($outlineColor !== '') {
            $colorLines[] = '名字外框：' . $this->translateLookupValue($outlineColor, $colorLookup, $colorTranslator);
        }
        if ($nameColor !== '') {
            $colorLines[] = '名字：' . $this->translateLookupValue($nameColor, $colorLookup, $colorTranslator);
        }
        if ($estColor !== '') {
            $colorLines[] = '年份：' . $this->translateLookupValue($estColor, $colorLookup, $colorTranslator);
        }

        if (!empty($colorLines)) {
            $this->setHeaderValue($values, '胸部文本颜色', implode("\n", $colorLines));
        }

        $haloOrWings = strtolower($this->firstExactAttributeValue($attributes, 'Pet Add Angel Halo or Wings'));
        $styleLines = [];

        if (strpos($haloOrWings, 'angel wings') !== false) {
            $styleLines[] = '宠物上添加天使翅膀';
        }

        if (strpos($haloOrWings, 'angel halo') !== false) {
            $styleLines[] = '宠物上添加天使光环';
        }

        if (!empty($styleLines)) {
            $this->setHeaderValue($values, '胸口样式', implode("\n", $styleLines));
        }

        if ($sleeveIcon !== '') {
            $iconImage = $this->resolveOptionImage($context, $row, 'Choose Icon On Sleeve', $sleeveIcon);
            $this->setHeaderValue($values, '左袖图标', $iconImage !== '' ? $iconImage : $sleeveIcon);
        }

        if ($this->hasAnySleeveContent($values) && $estColor !== '') {
            $this->setHeaderValue($values, '袖子绣线颜色', $this->translateLookupValue($estColor, $colorLookup, $colorTranslator));
        } else {
            $this->setHeaderValue($values, '袖子绣线颜色', '');
        }
    }

    private function applyQk3961Rules(array &$values, array $attributes, array $row, array $context)
    {
        $photoTextLines = [];
        $topDesignLines = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            if (preg_match('/text below the photo\s*#?\s*(\d+)/i', $name, $matches)) {
                $photoTextLines[(int) $matches[1]] = $matches[1] . ': ' . $value;
                continue;
            }

            if (preg_match('/design on top of the pet\'?s head\s*#?\s*(\d+)/i', $name, $matches)) {
                $lowerValue = strtolower($value);
                $lineNumber = (int) $matches[1];

                if (strpos($lowerValue, 'golden angel halo') !== false) {
                    $topDesignLines[$lineNumber] = $lineNumber . ': 添加天使光环';
                } elseif (strpos($lowerValue, 'halo with gold wings') !== false) {
                    $topDesignLines[$lineNumber] = $lineNumber . ': 添加天使翅膀';
                }
            }
        }

        if (!empty($photoTextLines)) {
            ksort($photoTextLines);
            $this->setHeaderValue($values, '宠物名字胸部信息', implode("\n", array_values($photoTextLines)));
        }

        if (!empty($topDesignLines)) {
            ksort($topDesignLines);
            $this->setHeaderValue($values, '是否添加天使光环或翅膀', implode("\n", array_values($topDesignLines)));
        }

        $sleeveTargets = $this->qk3961SleeveTargets($this->firstExactAttributeValue($attributes, 'Add text somewhere else'));

        if (empty($sleeveTargets)) {
            return;
        }

        $fontStyle = $this->firstExactAttributeValue($attributes, 'Choose text font style');
        $sleevePattern = $this->firstExactAttributeValue($attributes, 'Choose Pattern on Sleeve');

        if ($fontStyle !== '') {
            $fontImage = $this->resolveOptionImage($context, $row, 'Choose text font style', $fontStyle);
            $fontValue = $fontImage !== '' ? $fontImage : $fontStyle;

            if (in_array('left', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '左袖字体', $fontValue);
            }

            if (in_array('right', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '右袖字体', $fontValue);
            }
        }

        if ($sleevePattern !== '') {
            $patternImage = $this->resolveOptionImage($context, $row, 'Choose Pattern on Sleeve', $sleevePattern);
            $patternValue = $patternImage !== '' ? $patternImage : $sleevePattern;

            if (in_array('left', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '左袖图标', $patternValue);
            }

            if (in_array('right', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '右袖图标', $patternValue);
            }
        }
    }

    private function qk3961SleeveTargets($value)
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return [];
        }

        if (strpos($value, 'both sleeves') !== false) {
            return ['left', 'right'];
        }

        if (strpos($value, 'left sleeve') !== false) {
            return ['left'];
        }

        if (strpos($value, 'right sleeve') !== false) {
            return ['right'];
        }

        return [];
    }

    private function applyMyx6625Rules(array &$values, array $attributes, array $row, array $context)
    {
        $textUnderPhoto = $this->firstExactAttributeValue($attributes, 'Text Under the Photo');

        if ($textUnderPhoto !== '') {
            $this->setHeaderValue($values, '宠物名字胸部信息', $textUnderPhoto);
        }

        $sleeveTargets = $this->myx6625SleeveTargets($this->firstExactAttributeValue($attributes, 'Add Text on Sleeve'));

        if (empty($sleeveTargets)) {
            return;
        }

        $fontStyle = $this->firstExactAttributeValue($attributes, 'Choose text font style');
        $sleeveIcon = $this->firstExactAttributeValue($attributes, 'Choose Icon on Sleeve');

        if ($fontStyle !== '') {
            $fontImage = $this->resolveOptionImage($context, $row, 'Choose text font style', $fontStyle);
            $fontValue = $fontImage !== '' ? $fontImage : $fontStyle;

            if (in_array('left', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '左袖字体', $fontValue);
            }

            if (in_array('right', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '右袖字体', $fontValue);
            }
        }

        if ($sleeveIcon !== '') {
            $iconImage = $this->resolveOptionImage($context, $row, 'Choose Icon on Sleeve', $sleeveIcon);
            $iconValue = $iconImage !== '' ? $iconImage : $sleeveIcon;

            if (in_array('left', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '左袖图标', $iconValue);
            }

            if (in_array('right', $sleeveTargets, true)) {
                $this->setHeaderValue($values, '右袖图标', $iconValue);
            }
        }
    }

    private function myx6625SleeveTargets($value)
    {
        $value = strtolower(trim((string) $value));
        $targets = [];

        if (strpos($value, 'left sleeve') !== false) {
            $targets[] = 'left';
        }

        if (strpos($value, 'right sleeve') !== false) {
            $targets[] = 'right';
        }

        return $targets;
    }

    private function applyOutlineThreadColorRules(array &$values, array $attributes, array $colorLookup, $colorTranslator)
    {
        if (($values[$this->headerIndex('全彩/轮廓')] ?? '') !== '轮廓') {
            return;
        }

        $threadColor = $this->firstExactAttributeValue($attributes, 'Thread Color');
        $outlineColor = $threadColor !== '' ? $threadColor : $this->firstExactAttributeValue($attributes, 'Choose Thread Color');

        if ($outlineColor === '') {
            return;
        }

        $translatedColor = $this->translateLookupValue($outlineColor, $colorLookup, $colorTranslator);
        $this->setHeaderValue($values, '图片轮廓线色', $translatedColor);

        if ($threadColor === '') {
            return;
        }

        if ($this->hasAnySleeveContent($values)) {
            $this->setHeaderValue($values, '袖子绣线颜色', $translatedColor);
        } else {
            $this->setHeaderValue($values, '袖子绣线颜色', '');
        }
    }

    private function isQk0833(array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === 'CS-QK0833-CX' || strpos($sku, 'CS-QK0833-CX') !== false;
    }

    private function isQk3961(array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === 'CS-QK3961-CX' || strpos($sku, 'CS-QK3961-CX') !== false;
    }

    private function isMyx6625(array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === 'CS-MYX6625-CX' || strpos($sku, 'CS-MYX6625-CX') !== false;
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

    private function hasAttributeNameContaining(array $attributes, $needle)
    {
        foreach ($attributes as $attribute) {
            if (strpos(strtolower((string) ($attribute['name'] ?? '')), strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function hasAnySleeveContent(array $values)
    {
        return $this->hasLeftSleeveContent($values) || $this->hasRightSleeveContent($values);
    }

    private function hasLeftSleeveContent(array $values)
    {
        $leftText = $values[$this->headerIndex('左袖信息')] ?? '';
        $leftIcon = $values[$this->headerIndex('左袖图标')] ?? '';

        return $leftText !== '' || $leftIcon !== '';
    }

    private function hasRightSleeveContent(array $values)
    {
        $rightText = $values[$this->headerIndex('右袖信息')] ?? '';
        $rightIcon = $values[$this->headerIndex('右袖图标')] ?? '';

        return $rightText !== '' || $rightIcon !== '';
    }
}
