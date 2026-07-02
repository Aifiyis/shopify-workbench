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

        $photoCaption = $this->firstExactAttributeValue($allAttributes, 'Text Under the Photo');
        if ($photoCaption !== '') {
            $this->setHeaderValue($values, '图片1下方文字', $photoCaption);
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
                $context['color_lookup'] ?? [],
                $context['color_translation_resolver'] ?? null
            );
        }

        $this->applyOutlineThreadColorRules(
            $values,
            $allAttributes,
            $context['color_lookup'] ?? [],
            $context['color_translation_resolver'] ?? null
        );

        return $values;
    }

    private function applyQk0833Rules(array &$values, array $attributes, array $colorLookup, $colorTranslator)
    {
        $petName = $this->firstExactAttributeValue($attributes, 'Pet Name');
        $est = $this->firstExactAttributeValue($attributes, 'EST');
        $outlineColor = $this->firstExactAttributeValue($attributes, 'Thread Color For Pet Name Outline');
        $nameColor = $this->firstExactAttributeValue($attributes, 'Thread Color For Pet Name');
        $estColor = $this->firstExactAttributeValue($attributes, 'Thread Color For EST and Other Text');

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

        if ($this->hasAnySleeveContent($values) && $estColor !== '') {
            $this->setHeaderValue($values, '袖子绣线颜色', $this->translateLookupValue($estColor, $colorLookup, $colorTranslator));
        } else {
            $this->setHeaderValue($values, '袖子绣线颜色', '');
        }
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

    private function firstExactAttributeValue(array $attributes, $targetName)
    {
        foreach ($attributes as $attribute) {
            if (strcasecmp(trim((string) ($attribute['name'] ?? '')), $targetName) === 0) {
                return trim((string) ($attribute['value'] ?? ''));
            }
        }

        return '';
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
