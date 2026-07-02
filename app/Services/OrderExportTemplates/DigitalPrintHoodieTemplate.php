<?php

namespace App\Services\OrderExportTemplates;

class DigitalPrintHoodieTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'digital_print_hoodie';
    protected $templateLabel = '数码印卫衣';
    protected $chineseNames = ['数码印卫衣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '产品图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '位置', '工艺', '图片数量', '图片链接', '贺卡',
    ];
}
