<?php

namespace App\Services\OrderExportTemplates;

class FoamHoodieTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'foam_hoodie';
    protected $templateLabel = '发泡卫衣';
    protected $chineseNames = ['发泡卫衣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '发泡颜色', '左袖信息', '左袖发泡符号', '左袖信息发泡颜色', '袖子位置', '右袖信息',
        '右袖发泡符号', '右袖信息发泡颜色', '袖子位置', '胸口信息字体', '胸口信息1', '胸口位置',
        '贺卡/包装',
    ];
}
