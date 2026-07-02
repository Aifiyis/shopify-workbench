<?php

namespace App\Services\OrderExportTemplates;

class AppliqueEmbroideryTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'applique_embroidery';
    protected $templateLabel = '贴布绣';
    protected $chineseNames = ['贴布绣', '亮片贴布绣', '亮片贴布绣文字刺绣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '左袖信息', '左袖符号1', '备注', '袖子位置', '右袖信息', '右袖符号', '袖子位置', '袖子绣线颜色',
        '袖子闪片颜色', '设计稿', '胸口信息', '胸口图片', '胸部文本字体', '小文本字体', '闪片颜色',
        '闪片文字外框颜色', '花体文字闪粉颜色', '亮片颜色', '背景颜色', '胸部文本颜色', '胸部小文本颜色',
        '胸部文本边框颜色', '胸部位置', '贺卡/礼品', '备注',
    ];
}
