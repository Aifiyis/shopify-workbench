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
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $this->setHeaderValue($values, '图片1', $photo);
        }

        if (($row['chinese_name'] ?? '') === '宠物轮廓') {
            $this->setHeaderValue($values, '全彩/轮廓', '轮廓');
        }

        if (($row['chinese_name'] ?? '') === '宠物彩图') {
            $this->setHeaderValue($values, '全彩/轮廓', '全彩');
        }

        return $values;
    }
}
