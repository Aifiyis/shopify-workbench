<?php

namespace App\Services\OrderExportTemplates;

class DigitalPrintTShirtTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'digital_print_tshirt';
    protected $templateLabel = '数码印短袖';
    protected $chineseNames = ['数码印短袖', '数码印T恤', '数码印衬衫'];
    protected $templateHeaders = [
        '导表日期', '订单号', '产品图', '是否做货', '是否发货', '产品类型', '衣服颜色', '尺码', '数量',
        '胸口文本', '文本位置', '工艺', '小孩名', '图片1', '图片2', '图片3', '图片4', '图片5', '图片6',
        '图片7', '图片8', '图片9', '备注', '预览图', '贺卡/包装',
    ];
}
