<?php

namespace Tests\Unit;

use App\Services\OrderExportTemplates\OrderExportTemplateRegistry;
use Tests\TestCase;

class OrderExportTemplateRegistryTest extends TestCase
{
    public function test_resolves_configured_chinese_names_to_templates()
    {
        $registry = OrderExportTemplateRegistry::default();

        $this->assertSame('ctcx', $registry->forChineseName('彩图刺绣')->key());
        $this->assertSame('neck_hole_embroidery', $registry->forChineseName('领口破洞刺绣')->key());
        $this->assertSame('pet_outline_color', $registry->forChineseName('宠物轮廓')->key());
        $this->assertSame('pet_outline_color', $registry->forChineseName('宠物彩图')->key());
        $this->assertSame('pet_outline_color', $registry->forChineseName('宠物轮廓彩图')->key());
        $this->assertSame('big_number_heat_transfer_hoodie', $registry->forChineseName('大数字烫画卫衣')->key());
        $this->assertSame('person_outline_color', $registry->forChineseName('人物轮廓')->key());
        $this->assertSame('person_outline_color', $registry->forChineseName('人物彩图')->key());
        $this->assertSame('person_outline_color', $registry->forChineseName('人物轮廓彩图')->key());
        $this->assertSame('heat_transfer_clothing', $registry->forChineseName('普通烫画卫衣')->key());
        $this->assertSame('heat_transfer_clothing', $registry->forChineseName('普通烫画衣服')->key());
        $this->assertSame('style_image_heat_transfer', $registry->forChineseName('款式图烫画')->key());
        $this->assertSame('text_embroidery', $registry->forChineseName('文字款刺绣')->key());
    }

    public function test_returns_null_for_unconfigured_chinese_name()
    {
        $registry = OrderExportTemplateRegistry::default();

        $this->assertNull($registry->forChineseName('毛毯'));
        $this->assertNull($registry->forChineseName(''));
    }
}
