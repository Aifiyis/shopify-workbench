<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\AppliqueEmbroideryTemplate;
use App\Services\OrderExportTemplates\CarEmbroideryTemplate;
use App\Services\OrderExportTemplates\DigitalPrintHoodieTemplate;
use App\Services\OrderExportTemplates\DigitalPrintSetTemplate;
use App\Services\OrderExportTemplates\DigitalPrintShortsTemplate;
use App\Services\OrderExportTemplates\DigitalPrintTShirtTemplate;
use App\Services\OrderExportTemplates\DoubleSidedHoodieTemplate;
use App\Services\OrderExportTemplates\FoamHoodieTemplate;
use App\Services\OrderExportTemplates\HeatTransferPantsTemplate;
use App\Services\OrderExportTemplates\HemBowEmbroideryTemplate;
use App\Services\OrderExportTemplates\LineEmbroideryMomTemplate;
use App\Services\OrderExportTemplates\OrderExportTemplateRegistry;
use App\Services\OrderExportTemplates\PatchworkHoodieTemplate;
use App\Services\OrderExportTemplates\ThreeDimensionalEmbroideryTemplate;
use App\Services\OrderExportTemplates\TowelEmbroideryTemplate;
use Tests\TestCase;

class ExpandedTemplateRegistryTest extends TestCase
{
    public function test_new_template_aliases_resolve_to_expected_keys()
    {
        $registry = OrderExportTemplateRegistry::default();
        $aliases = [
            '发泡卫衣' => 'foam_hoodie',
            '拼接卫衣' => 'patchwork_hoodie',
            '烫画裤子' => 'heat_transfer_pants',
            '数码印短袖' => 'digital_print_tshirt',
            '数码印T恤' => 'digital_print_tshirt',
            '数码印衬衫' => 'digital_print_tshirt',
            '数码印套装' => 'digital_print_set',
            '数码印卫衣' => 'digital_print_hoodie',
            '贴布绣' => 'applique_embroidery',
            '亮片贴布绣' => 'applique_embroidery',
            '亮片贴布绣文字刺绣' => 'applique_embroidery',
            '线条刺绣妈妈款' => 'line_embroidery_mom',
            '立体绣' => 'three_dimensional_embroidery',
            '双面卫衣' => 'double_sided_hoodie',
            '双面卫衣-烫画' => 'double_sided_hoodie',
            '双面卫衣烫画' => 'double_sided_hoodie',
            '毛巾绣' => 'towel_embroidery',
            '下摆蝴蝶结刺绣' => 'hem_bow_embroidery',
            '汽车刺绣' => 'car_embroidery',
            '数码印短裤' => 'digital_print_shorts',
        ];

        foreach ($aliases as $name => $key) {
            $template = $registry->forChineseName($name);
            $this->assertNotNull($template, $name);
            $this->assertSame($key, $template->key(), $name);
        }
    }

    public function test_new_templates_preserve_source_headers_and_append_audit_columns()
    {
        $expectations = [
            [new FoamHoodieTemplate(), ['导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '发泡颜色', '左袖信息', '左袖发泡符号', '左袖信息发泡颜色', '袖子位置', '右袖信息', '右袖发泡符号', '右袖信息发泡颜色', '袖子位置', '胸口信息字体', '胸口信息1', '胸口位置', '贺卡/包装']],
            [new PatchworkHoodieTemplate(), ['导表日期', '订单号', '主图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '左袖信息', '左袖符号', '袖子位置', '右袖信息', '右袖符号', '袖子位置', '袖子绣线颜色', '设计稿', '胸口信息', '胸口大文本/学校名颜色', '胸口大文本背景颜色', '胸口小文本/队伍名颜色', '闪片1', '闪片2', '胸口位置', '备注']],
            [new HeatTransferPantsTemplate(), ['导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '裤子颜色', '尺码', '数量', '设计图', '臀部信息', '烫画位置', '设计风格', '贺卡/包装']],
            [new DigitalPrintTShirtTemplate(), ['导表日期', '订单号', '产品图', '是否做货', '是否发货', '产品类型', '衣服颜色', '尺码', '数量', '胸口文本', '文本位置', '工艺', '小孩名', '图片1', '图片2', '图片3', '图片4', '图片5', '图片6', '图片7', '图片8', '图片9', '备注', '预览图', '贺卡/包装']],
            [new DigitalPrintSetTemplate(), ['导表日期', '订单号', '产品图', '产品图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '左袖文本', '袖子位置', '胸口文本', '胸口文本位置', '工艺', '图片位置', '图片1', '是否做货']],
            [new DigitalPrintHoodieTemplate(), ['导表日期', '订单号', '产品图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '位置', '工艺', '图片数量', '图片链接', '贺卡']],
            [new AppliqueEmbroideryTemplate(), ['导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '左袖信息', '左袖符号1', '备注', '袖子位置', '右袖信息', '右袖符号', '袖子位置', '袖子绣线颜色', '袖子闪片颜色', '设计稿', '胸口信息', '胸口图片', '胸部文本字体', '小文本字体', '闪片颜色', '闪片文字外框颜色', '花体文字闪粉颜色', '亮片颜色', '背景颜色', '胸部文本颜色', '胸部小文本颜色', '胸部文本边框颜色', '胸部位置', '贺卡/礼品', '备注']],
            [new LineEmbroideryMomTemplate(), ['导表日期', '订单号', '款图', '是否做货', '款式', '衣服颜色', '尺码', '数量', '左袖文本', '左袖图标', '左袖文本字体', '袖子位置', '右袖文本', '右袖图标', '袖子位置', '袖子绣线颜色', '胸部文本', '胸部文本颜色', '胸部文本字体', '胸部位置', '贺卡/礼品']],
            [new ThreeDimensionalEmbroideryTemplate(), ['导表日期', '订单号', '款图', '是否做货', '款式', '衣服颜色', '尺码', '数量', '左袖文本', '左袖图标', '袖子位置', '右袖文本', '右袖图标', '袖子位置', '袖子线色', '设计稿', '领口信息', '领口位置', '胸口信息', '胸口文本颜色', '胸部位置']],
            [new DoubleSidedHoodieTemplate(), ['导表时间', '订单号', '款式图', '是否做货', '款式', '衣服颜色', '尺码', '数量', '胸口外侧信息', '位置', '胸口内侧信息', '位置', '外侧文本颜色', '外侧文本颜色', '内侧文本颜色', '内侧文本颜色', '贺卡/礼品']],
            [new TowelEmbroideryTemplate(), ['导表日期', '订单号', '款式图', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '文本颜色', '文本字体', '左袖信息', '左袖图标1', '袖子位置', '右袖信息', '右袖图标1', '袖子位置', '袖子绣线颜色', '设计稿', '胸口信息', '胸部位置', '贺卡/包装']],
            [new HemBowEmbroideryTemplate(), ['导表日期', '订单号', '订单图片', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '左袖信息', '左袖符号', '袖子位置', '右袖信息', '右袖符号', '袖子位置', '袖子绣线颜色', '设计稿', '胸口文本', '胸口文本风格', '胸部位置', '胸口文本颜色', '胸口文本闪片颜色', '胸口文本格纹颜色', '胸口文本闪片', '胸口文本边框颜色', '背景闪片', '蝴蝶结颜色', '蝴蝶结边框颜色', '蝴蝶结格纹颜色', '蝴蝶结闪片颜色', '蝴蝶结下摆是否加火焰', '蝴蝶结样式', '备注']],
            [new CarEmbroideryTemplate(), ['导表日期', '订单号', '订单图片', '是否做货', '是否发货', '款式', '衣服颜色', '尺码', '数量', '左袖信息', '左袖图标', '袖子位置', '右袖信息', '右袖图标', '袖子位置', '袖子绣线颜色', '全彩/轮廓', '定制照片', '照片下方文字', '胸部信息', '胸部信息颜色', '图片轮廓色', '胸部位置', '备注']],
            [new DigitalPrintShortsTemplate(), ['导表日期', '订单号', '产品图', '是否做货', '是否发货', '产品类型', '衣服颜色', '尺码', '数量', '工艺', '备注', '贺卡/包装', '对账类型', '店铺类型', '编码', '产品单价', '产品总价']],
        ];

        foreach ($expectations as [$template, $sourceHeaders]) {
            $headers = $template->headers();
            $this->assertSame($sourceHeaders, array_slice($headers, 0, -4), $template->label());
            $this->assertSame(['产品规格', 'sku', 'cleaned_sku', '产品链接'], array_slice($headers, -4), $template->label());
        }
    }
}
