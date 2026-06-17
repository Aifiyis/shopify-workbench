<?php

namespace App\Services\OrderExportTemplates;

class StyleImageHeatTransferTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'style_image_heat_transfer';
    }

    public function label()
    {
        return '款式图烫画';
    }

    public function supportedChineseNames()
    {
        return ['款式图烫画'];
    }

    public function headers()
    {
        return $this->withProductSpecsHeader([
            '导表日期',
            '订单号',
            '款式图',
            '是否做货',
            '是否发货',
            '款式图',
            '衣服颜色',
            '尺码',
            '数量',
            '设计图',
            '胸口信息',
            '后背信息',
            '胸口文本颜色',
            '后背文本颜色',
            '烫画位置',
            '设计风格',
            '贺卡/包装',
        ]);
    }
}
