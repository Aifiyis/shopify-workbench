<?php

namespace App\Services\OrderExportTemplates;

class BigNumberHeatTransferHoodieTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'big_number_heat_transfer_hoodie';
    }

    public function label()
    {
        return '大数字烫画卫衣';
    }

    public function supportedChineseNames()
    {
        return ['大数字烫画卫衣'];
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
            '左袖信息',
            '左袖图标',
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '备注',
            '袖子位置',
            '袖子线色',
            '设计稿',
            '胸口信息',
            '胸口信息颜色',
            '胸口位置',
            '贺卡/包装',
        ]);
    }
}
