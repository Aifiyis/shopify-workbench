<?php

namespace App\Services\OrderExportTemplates;

class LineEmbroideryMomTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'line_embroidery_mom';
    protected $templateLabel = '线条刺绣妈妈款';
    protected $chineseNames = ['线条刺绣妈妈款'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款图', '是否做货', '款式', '衣服颜色', '尺码', '数量', '左袖文本', '左袖图标',
        '左袖文本字体', '袖子位置', '右袖文本', '右袖图标', '袖子位置', '袖子绣线颜色', '胸部文本',
        '胸部文本颜色', '胸部文本字体', '胸部位置', '贺卡/礼品',
    ];
}
