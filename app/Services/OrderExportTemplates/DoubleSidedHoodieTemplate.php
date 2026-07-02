<?php

namespace App\Services\OrderExportTemplates;

class DoubleSidedHoodieTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'double_sided_hoodie';
    protected $templateLabel = '双面卫衣';
    protected $chineseNames = ['双面卫衣', '双面卫衣-烫画', '双面卫衣烫画'];
    protected $templateHeaders = [
        '导表时间', '订单号', '款式图', '是否做货', '款式', '衣服颜色', '尺码', '数量', '胸口外侧信息',
        '位置', '胸口内侧信息', '位置', '外侧文本颜色', '外侧文本颜色', '内侧文本颜色', '内侧文本颜色',
        '贺卡/礼品',
    ];
}
