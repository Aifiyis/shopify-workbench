<?php

namespace App\Services\OrderExportTemplates;

class HemBowEmbroideryTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'hem_bow_embroidery';
    protected $templateLabel = '下摆蝴蝶结刺绣';
    protected $chineseNames = ['下摆蝴蝶结刺绣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '订单图片', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '左袖信息', '左袖符号', '袖子位置', '右袖信息', '右袖符号', '袖子位置', '袖子绣线颜色', '设计稿',
        '胸口文本', '胸口文本风格', '胸部位置', '胸口文本颜色', '胸口文本闪片颜色', '胸口文本格纹颜色',
        '胸口文本闪片', '胸口文本边框颜色', '背景闪片', '蝴蝶结颜色', '蝴蝶结边框颜色', '蝴蝶结格纹颜色',
        '蝴蝶结闪片颜色', '蝴蝶结下摆是否加火焰', '蝴蝶结样式', '备注',
    ];
}
