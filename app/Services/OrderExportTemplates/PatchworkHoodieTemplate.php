<?php

namespace App\Services\OrderExportTemplates;

class PatchworkHoodieTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'patchwork_hoodie';
    protected $templateLabel = '拼接卫衣';
    protected $chineseNames = ['拼接卫衣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '主图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '左袖信息', '左袖符号', '袖子位置', '右袖信息', '右袖符号', '袖子位置', '袖子绣线颜色',
        '设计稿', '胸口信息', '胸口大文本/学校名颜色', '胸口大文本背景颜色', '胸口小文本/队伍名颜色',
        '闪片1', '闪片2', '胸口位置', '备注',
    ];
}
