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
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $text = $this->firstAttributeValue($attributes, ['text']);
        $threadColor = $this->firstAttributeValue($attributes, ['thread color']);
        $printColor = $this->firstAttributeValue($attributes, ['print color']);
        $position = $this->firstAttributeValue($attributes, ['position']);

        if ($text !== '') {
            $this->setHeaderValue($values, '胸口信息', $text);
        }

        if ($threadColor !== '') {
            $this->setHeaderValue($values, '胸口文本颜色', $this->translateLookupValue($threadColor, $context['color_lookup'] ?? []));
        }

        if ($printColor !== '') {
            $this->setHeaderValue($values, '袖子绣线颜色', $this->translateLookupValue($printColor, $context['color_lookup'] ?? []));
        }

        if ($position !== '') {
            $this->setFirstHeaderValue($values, ['胸口位置', '领口位置'], $this->mapEmbroideryPosition($position));
        }

        return $values;
    }
}
