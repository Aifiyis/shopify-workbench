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
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $this->setHeaderValue($values, '设计稿', $photo);
        }

        return $values;
    }
}
