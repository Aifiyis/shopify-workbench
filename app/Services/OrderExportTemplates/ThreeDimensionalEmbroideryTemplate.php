<?php

namespace App\Services\OrderExportTemplates;

class ThreeDimensionalEmbroideryTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'three_dimensional_embroidery';
    protected $templateLabel = '立体绣';
    protected $chineseNames = ['立体绣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款图', '是否做货', '款式', '衣服颜色', '尺码', '数量', '左袖文本', '左袖图标',
        '袖子位置', '右袖文本', '右袖图标', '袖子位置', '袖子线色', '设计稿', '领口信息', '领口位置',
        '胸口信息', '胸口文本颜色', '胸部位置',
    ];
}
