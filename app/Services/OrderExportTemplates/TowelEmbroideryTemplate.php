<?php

namespace App\Services\OrderExportTemplates;

class TowelEmbroideryTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'towel_embroidery';
    protected $templateLabel = '毛巾绣';
    protected $chineseNames = ['毛巾绣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '文本颜色', '文本字体', '左袖信息', '左袖图标1', '袖子位置', '右袖信息', '右袖图标1', '袖子位置',
        '袖子绣线颜色', '设计稿', '胸口信息', '胸部位置', '贺卡/包装',
    ];
}
