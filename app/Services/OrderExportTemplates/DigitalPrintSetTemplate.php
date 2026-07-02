<?php

namespace App\Services\OrderExportTemplates;

class DigitalPrintSetTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'digital_print_set';
    protected $templateLabel = '数码印套装';
    protected $chineseNames = ['数码印套装'];
    protected $templateHeaders = [
        '导表日期', '订单号', '产品图', '产品图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '左袖文本', '袖子位置', '胸口文本', '胸口文本位置', '工艺', '图片位置', '图片1', '是否做货',
    ];
}
