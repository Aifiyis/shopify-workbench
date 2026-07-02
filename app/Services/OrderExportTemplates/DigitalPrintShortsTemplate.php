<?php

namespace App\Services\OrderExportTemplates;

class DigitalPrintShortsTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'digital_print_shorts';
    protected $templateLabel = '数码印短裤';
    protected $chineseNames = ['数码印短裤'];
    protected $templateHeaders = [
        '导表日期', '订单号', '产品图', '是否做货', '是否发货', '产品类型', '衣服颜色', '尺码', '数量',
        '工艺', '备注', '贺卡/包装', '对账类型', '店铺类型', '编码', '产品单价', '产品总价',
    ];
}
