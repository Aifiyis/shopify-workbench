<?php

namespace App\Services\OrderExportTemplates;

class HeatTransferPantsTemplate extends StaticHeaderOrderExportTemplate
{
    protected $templateKey = 'heat_transfer_pants';
    protected $templateLabel = '烫画裤子';
    protected $chineseNames = ['烫画裤子'];
    protected $templateHeaders = [
        '导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '裤子颜色', '尺码', '数量',
        '设计图', '臀部信息', '烫画位置', '设计风格', '贺卡/包装',
    ];
}
