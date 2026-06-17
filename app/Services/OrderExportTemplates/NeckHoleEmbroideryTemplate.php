<?php

namespace App\Services\OrderExportTemplates;

class NeckHoleEmbroideryTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'neck_hole_embroidery';
    }

    public function label()
    {
        return '领口破洞刺绣';
    }

    public function supportedChineseNames()
    {
        return ['领口破洞刺绣'];
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
            '左袖符号',
            '左袖线色',
            '袖子位置',
            '右袖信息',
            '右袖符号',
            '备注',
            '右袖线色',
            '袖子位置',
            '领口信息',
            '领口文本颜色',
            '刺绣位置',
            '贺卡/礼品',
            '备注',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $threadColor = $this->firstAttributeValue($attributes, ['thread color']);
        $text = $this->firstAttributeValue($attributes, ['text']);
        $collarEmbroidery = $this->firstAttributeValue($attributes, ['collar embroidery']);
        $embroideryPosition = $this->firstAttributeValue($attributes, ['embroidery', 'position']);

        if ($collarEmbroidery !== '') {
            $this->setHeaderValueIfBlank($values, '领口信息', $collarEmbroidery);
        } elseif ($text !== '') {
            $this->setHeaderValueIfBlank($values, '领口信息', $text);
        }

        if ($threadColor !== '') {
            $this->setHeaderValueIfBlank($values, '领口文本颜色', $this->translateLookupValue($threadColor, $context['color_lookup'] ?? []));
        }

        if ($embroideryPosition !== '') {
            $this->setHeaderValueIfBlank($values, '刺绣位置', $embroideryPosition);
        } else {
            $this->setHeaderValueIfBlank($values, '刺绣位置', '左领口');
        }

        return $values;
    }
}
