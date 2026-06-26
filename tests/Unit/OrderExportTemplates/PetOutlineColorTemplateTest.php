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

    public function test_qk3961_maps_photo_text_and_both_sleeve_images()
    {
        $template = new PetOutlineColorTemplate();
        $resolver = new class {
            public function resolve($sku, $optionName, $optionValue)
            {
                if ($optionName === 'Choose text font style' && $optionValue === 'Font A') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-a.png';
                }

                if ($optionName === 'Choose Pattern on Sleeve' && $optionValue === 'Paw Pattern') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-pattern.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-6',
            'sku' => 'RAW-CS-QK3961-CX',
            'cleaned_sku' => 'CS-QK3961-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Text Below the photo 1: Luna',
                'Text Below the photo 2: 2024',
                'Add text somewhere else: Both Sleeves',
                'Choose text font style: Font A',
                'Choose Pattern on Sleeve: Paw Pattern',
                "Design on Top of the Pet's Head #1: Golden Angel Halo",
                "Design on Top of the Pet's Head #2: Halo With Gold wings",
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame("1: Luna\n2: 2024", $row[19]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-a.png', $row[11]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-a.png', $row[15]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-pattern.png', $row[10]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-pattern.png', $row[14]);
        $this->assertSame("1: 添加天使光环\n2: 添加天使翅膀", $row[23]);
    }

    public function test_qk3961_maps_single_sleeve_images_only_to_requested_side()
    {
        $template = new PetOutlineColorTemplate();
        $resolver = new class {
            public function resolve($sku, $optionName, $optionValue)
            {
                if ($optionName === 'Choose text font style' && $optionValue === 'Font B') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-b.png';
                }

                if ($optionName === 'Choose Pattern on Sleeve' && $optionValue === 'Star Pattern') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\star-pattern.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-7',
            'sku' => 'RAW-CS-QK3961-CX',
            'cleaned_sku' => 'CS-QK3961-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Add text somewhere else: Left Sleeve',
                'Choose text font style: Font B',
                'Choose Pattern on Sleeve: Star Pattern',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-b.png', $row[11]);
        $this->assertSame('', $row[15]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\star-pattern.png', $row[10]);
        $this->assertSame('', $row[14]);
    }

    public function test_number_of_pet_maps_numbered_photos_to_matching_image_columns()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-8',
            'sku' => 'RAW-CS-PET-PHOTOS',
            'cleaned_sku' => 'CS-PET-PHOTOS',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Number of Pet: 3',
                'Photo 3: https://example.test/pet-3.png',
                'Text Below the photo 2: This is caption text',
                'Photo 1: https://example.test/pet-1.png',
                'Photo 2: https://example.test/pet-2.png',
            ]),
        ], []);

        $this->assertSame('https://example.test/pet-1.png', $row[29]);
        $this->assertSame('https://example.test/pet-2.png', $row[31]);
        $this->assertSame('https://example.test/pet-3.png', $row[32]);
    }

    public function test_defaults_chest_position_to_center_when_specs_and_sku_have_no_position()
    {
        $template = new PetOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-10',
            'sku' => 'RAW-CS-PET-NO-POSITION',
            'cleaned_sku' => 'CS-PET-NO-POSITION',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
            ]),
        ], []);

        $this->assertSame('胸部中央', $row[25]);
    }

    public function test_qk0833_maps_choose_icon_on_sleeve_to_left_sleeve_icon_image()
    {
        $template = new PetOutlineColorTemplate();
        $resolver = new class {
            public function resolve($sku, $optionName, $optionValue)
            {
                if ($optionName === 'Choose Icon On Sleeve' && $optionValue === 'Paw Icon') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-icon.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-11',
            'sku' => 'RAW-CS-QK0833-CX',
            'cleaned_sku' => 'CS-QK0833-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Icon On Sleeve: Paw Icon',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-icon.png', $row[10]);
    }

    public function test_myx6625_maps_photo_text_and_sleeve_style_images()
    {
        $template = new PetOutlineColorTemplate();
        $resolver = new class {
            public function resolve($sku, $optionName, $optionValue)
            {
                if ($optionName === 'Choose text font style' && $optionValue === 'Script Font') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\script-font.png';
                }

                if ($optionName === 'Choose Icon on Sleeve' && $optionValue === 'Heart Icon') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\heart-icon.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PET-9',
            'sku' => 'RAW-CS-MYX6625-CX',
            'cleaned_sku' => 'CS-MYX6625-CX',
            'chinese_name' => '宠物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Text Under the Photo: Forever Loved',
                'Add Text on Sleeve: Left Sleeve + Right Sleeve',
                'Choose text font style: Script Font',
                'Choose Icon on Sleeve: Heart Icon',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('Forever Loved', $row[19]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\script-font.png', $row[11]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\script-font.png', $row[15]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\heart-icon.png', $row[10]);
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\heart-icon.png', $row[14]);
    }
}
