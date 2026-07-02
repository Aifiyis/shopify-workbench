<?php

namespace App\Services\OrderExportTemplates;

class CarEmbroideryTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'car_embroidery';
    protected $templateLabel = '汽车刺绣';
    protected $chineseNames = ['汽车刺绣'];
    protected $templateHeaders = [
        '导表日期', '订单号', '订单图片', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量',
        '左袖信息', '左袖图标', '袖子位置', '右袖信息', '右袖图标', '袖子位置', '袖子绣线颜色',
        '全彩/轮廓', '定制照片', '照片下方文字', '胸部信息', '胸部信息颜色', '图片轮廓色', '胸部位置', '备注',
    ];
}
