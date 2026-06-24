<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\PersonOutlineColorTemplate;
use Tests\TestCase;

class PersonOutlineColorTemplateTest extends TestCase
{
    public function test_defaults_chest_position_to_center_when_specs_and_sku_have_no_position()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-1',
            'sku' => 'UNMAPPED-PERSON-SKU',
            'cleaned_sku' => 'UNMAPPED-PERSON-SKU',
            'chinese_name' => '人物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
            ]),
        ], []);

        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '胸部位置'));
    }

    public function test_maps_photo_text_and_ignores_custom_own_nickname_placeholder()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-2',
            'sku' => 'PERSON-PHOTO-SKU',
            'cleaned_sku' => 'PERSON-PHOTO-SKU',
            'chinese_name' => '人物彩图',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Nickname: Custom Your Own',
                'Custom Text Unter The Nickname: Under Nick',
                'EST: 2020',
                'Year: 2024',
                'Upload Your Photo: https://example.test/base.jpg',
                'Upload Your Photo_1: https://example.test/one.jpg',
                'Upload Your Photo_2: https://example.test/two.jpg',
                'Upload Your Photo_6: https://example.test/six.jpg',
            ]),
        ], []);

        $this->assertSame('', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame("Under Nick\n2020\n2024", $this->valueForHeader($template, $row, '图片1下方文字'));
        $this->assertSame("https://example.test/base.jpg\nhttps://example.test/one.jpg", $this->valueForHeader($template, $row, '图片1'));
        $this->assertSame('https://example.test/two.jpg', $this->valueForHeader($template, $row, '图片2'));
        $this->assertSame('https://example.test/six.jpg', $this->valueForHeader($template, $row, '图片6'));
    }

    public function test_add_names_on_sleeve_maps_first_n_name_values_to_left_sleeve_info()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-3',
            'sku' => 'PERSON-SLEEVE-SKU',
            'cleaned_sku' => 'PERSON-SLEEVE-SKU',
            'chinese_name' => '人物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Add Names on Sleeve: 2',
                'Name #1: Alice',
                'Name #2: Bob',
                'Name #3: Charlie',
            ]),
        ], []);

        $this->assertSame("Alice\nBob", $this->valueForHeader($template, $row, '左袖信息'));
    }

    public function test_enter_your_title_does_not_map_to_chest_info_for_other_skus()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-4',
            'sku' => 'PERSON-TITLE-SKU',
            'cleaned_sku' => 'PERSON-TITLE-SKU',
            'chinese_name' => '人物彩图',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Enter Your Title: Best Dad',
            ]),
        ], []);

        $this->assertSame('', $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_hr2938_uses_choose_title_when_not_custom_and_maps_year_pattern_and_names()
    {
        $template = new PersonOutlineColorTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                if ($optionName === 'Choose Pattern under the names' && $optionValue === 'Heart') {
                    return 'https://example.test/heart.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-HR2938-1',
            'sku' => 'RAW-CS-HR2938-CX',
            'cleaned_sku' => 'CS-HR2938-CX',
            'chinese_name' => '人物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Your Title: Best Dad',
                'Enter Your Title: Custom Dad',
                'Year: EST.2026',
                'Choose Pattern under the names: Heart',
                'Name #1: Alice',
                'Name #2: Bob',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame("Best Dad\nEST. 2026", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('https://example.test/heart.png', $this->valueForHeader($template, $row, '左袖图标'));
        $this->assertSame("Alice\nBob", $this->valueForHeader($template, $row, '左袖信息'));
    }

    public function test_hr2938_uses_enter_title_when_choose_title_is_custom()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-HR2938-2',
            'sku' => 'RAW-CS-HR2938-CX',
            'cleaned_sku' => 'CS-HR2938-CX',
            'chinese_name' => '人物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Your Title: Custom Your Title',
                'Enter Your Title: Team Captain',
                'EST Year: 2024',
            ]),
        ], []);

        $this->assertSame("Team Captain\nEST. 2024", $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_hr2938_ignores_no_thanks_pattern_and_keeps_est_year_out_of_image_caption()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-HR2938-3',
            'sku' => 'RAW-CS-HR2938-CX',
            'cleaned_sku' => 'CS-HR2938-CX',
            'chinese_name' => '人物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Your Title: Best Dad',
                'EST Year: 2024',
                'Choose Pattern under the names: No Thanks',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
            ],
        ]);

        $this->assertSame("Best Dad\nEST. 2024", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '左袖图标'));
        $this->assertSame('', $this->valueForHeader($template, $row, '图片1下方文字'));
    }

    public function test_outline_thread_color_maps_to_outline_and_sleeve_color_when_sleeve_content_exists()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-5',
            'sku' => 'PERSON-OUTLINE-SKU',
            'cleaned_sku' => 'PERSON-OUTLINE-SKU',
            'chinese_name' => '人物轮廓',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Text: Lily',
                'Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Red' => '红色',
            ],
        ]);

        $this->assertSame('轮廓', $this->valueForHeader($template, $row, '全彩/轮廓'));
        $this->assertSame('红色', $this->valueForHeader($template, $row, '图片轮廓线色'));
        $this->assertSame('红色', $this->valueForHeader($template, $row, '袖子绣线颜色'));
    }

    public function test_outline_thread_color_does_not_map_to_sleeve_color_without_sleeve_content()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-6',
            'sku' => 'PERSON-OUTLINE-SKU-2',
            'cleaned_sku' => 'PERSON-OUTLINE-SKU-2',
            'chinese_name' => '人物轮廓',
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

        $this->assertSame('红色', $this->valueForHeader($template, $row, '图片轮廓线色'));
        $this->assertSame('', $this->valueForHeader($template, $row, '袖子绣线颜色'));
    }

    public function test_full_color_center_chest_maps_non_sleeve_thread_color_to_outline_color()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-7',
            'sku' => 'PERSON-FULL-CENTER-SKU',
            'cleaned_sku' => 'PERSON-FULL-CENTER-SKU',
            'chinese_name' => '人物彩图',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Thread Color: Blue',
                'Left Sleeve Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
                'Blue' => '蓝色',
                'Red' => '红色',
            ],
        ]);

        $this->assertSame('全彩', $this->valueForHeader($template, $row, '全彩/轮廓'));
        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '胸部位置'));
        $this->assertSame('蓝色', $this->valueForHeader($template, $row, '图片轮廓线色'));
    }

    public function test_upload_your_photo_numbered_options_map_to_matching_image_columns()
    {
        $template = new PersonOutlineColorTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-8',
            'sku' => 'PERSON-PHOTO-NUMBERED-SKU',
            'cleaned_sku' => 'PERSON-PHOTO-NUMBERED-SKU',
            'chinese_name' => '人物彩图',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Upload Your Photo 1: https://example.test/one.jpg',
                'Upload Your Photo 2: https://example.test/two.jpg',
                'Upload Your Photo 6: https://example.test/six.jpg',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
            ],
        ]);

        $this->assertSame('https://example.test/one.jpg', $this->valueForHeader($template, $row, '图片1'));
        $this->assertSame('https://example.test/two.jpg', $this->valueForHeader($template, $row, '图片2'));
        $this->assertSame('https://example.test/six.jpg', $this->valueForHeader($template, $row, '图片6'));
    }

    public function test_hr2572_builds_chest_info_left_sleeve_info_and_font_style_image()
    {
        $template = new PersonOutlineColorTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                if ($cleanedSku === 'CS-HR2572-CX'
                    && $optionName === 'Choose Font For Nickname'
                    && $optionValue === 'Font A') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-a.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-PERSON-HR2572',
            'sku' => 'RAW-CS-HR2572-CX',
            'cleaned_sku' => 'CS-HR2572-CX',
            'chinese_name' => '人物彩图',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Enter Nickname: Nana',
                'Custom a Loving Message Below the Photo: Love you',
                'Add Name on the Left Sleeve?: Yes',
                'Name on the Left Sleeve: Lily',
                'Choose Font For Nickname: Font A',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
            ],
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame("Nana\nLove you", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('Lily', $this->valueForHeader($template, $row, '左袖信息'));
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\font-a.png', $this->valueForHeader($template, $row, '胸口样式'));
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
