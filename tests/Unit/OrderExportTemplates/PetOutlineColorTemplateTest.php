<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\PetOutlineColorTemplate;
use Tests\TestCase;

class PetOutlineColorTemplateTest extends TestCase
{
    public function test_qk0833_maps_pet_text_colors_sleeves_and_outline_thread_color()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-1',
            'sku' => 'RAW-CS-QK0833-CX',
            'cleaned_sku' => 'CS-QK0833-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Text: Luna',
                'Left Sleeve Icon: Heart',
                'Right Sleeve Icon: Star',
                'Pet Name: Fluffy',
                'EST: 2024',
                'Pet Add Angel Halo or Wings: Angel Wings + Angel Halo',
                'Thread Color For Pet Name Outline: White',
                'Thread Color For Pet Name: Black',
                'Thread Color For EST and Other Text: Gold',
                'Choose Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'White' => '白色',
                'Black' => '黑色',
                'Gold' => '金色',
            ],
        ]);

        $this->assertSame("名字外框：白色\n名字：黑色\n年份：金色", $row[21]);
        $this->assertSame("宠物上添加天使翅膀\n宠物上添加天使光环", $row[18]);
        $this->assertSame('Fluffy', $row[19]);
        $this->assertSame('2024', $row[20]);
        $this->assertSame('Red', $row[27]);
        $this->assertSame('左袖', $row[12]);
        $this->assertSame('右袖', $row[16]);
        $this->assertSame('金色', $row[17]);
    }

    public function test_qk0833_sets_sleeve_thread_color_when_any_sleeve_text_or_icon_exists()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-2',
            'sku' => 'RAW-CS-QK0833-CX',
            'cleaned_sku' => 'CS-QK0833-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Text: Luna',
                'Thread Color For EST and Other Text: Silver',
                'Choose Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Silver' => '银色',
            ],
        ]);

        $this->assertSame('Red', $row[27]);
        $this->assertSame('左袖', $row[12]);
        $this->assertSame('', $row[16]);
        $this->assertSame('银色', $row[17]);
    }

    public function test_qk0833_leaves_sleeve_thread_color_blank_without_sleeve_content()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-3',
            'sku' => 'RAW-CS-QK0833-CX',
            'cleaned_sku' => 'CS-QK0833-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Thread Color For EST and Other Text: Silver',
                'Choose Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Silver' => '银色',
            ],
        ]);

        $this->assertSame('Red', $row[27]);
        $this->assertSame('', $row[12]);
        $this->assertSame('', $row[16]);
        $this->assertSame('', $row[17]);
    }

    public function test_outline_thread_color_maps_to_outline_and_sleeve_thread_color_when_sleeve_content_exists()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-4',
            'sku' => 'RAW-CS-PET-OUTLINE',
            'cleaned_sku' => 'CS-PET-OUTLINE',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Text: Luna',
                'Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Red' => '红色',
            ],
        ]);

        $this->assertSame('红色', $row[27]);
        $this->assertSame('红色', $row[17]);
    }

    public function test_outline_thread_color_maps_to_outline_only_without_sleeve_content()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-5',
            'sku' => 'RAW-CS-PET-OUTLINE',
            'cleaned_sku' => 'CS-PET-OUTLINE',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Red' => '红色',
            ],
        ]);

        $this->assertSame('红色', $row[27]);
        $this->assertSame('', $row[17]);
    }
}
