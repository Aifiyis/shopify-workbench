<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\CtcxTemplate;
use Tests\TestCase;

class CtcxTemplateTest extends TestCase
{
    public function test_qk0743_builds_chest_text_color_and_position()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-1',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK0743-CX-001',
            'cleaned_sku' => 'ABC-CS-QK0743-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'State Options: California',
                'Year: EST.2026',
                'Text Thread Color: Red',
            ]),
        ], [
            'color_lookup' => ['Red' => '红色'],
        ]);

        $this->assertSame("第一行：California\n第二行：EST. 2026", $this->valueForHeader($template, $row, '胸部信息'));
        $this->assertSame('红色', $this->valueForHeader($template, $row, '胸部信息颜色'));
        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '胸部位置'));
    }

    public function test_qk2571_builds_full_color_photo_and_position()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-2',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK2571-CX-001',
            'cleaned_sku' => 'ABC-CS-QK2571-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'Thread Color: Gold',
                'Embroidery Position: Middle Chest',
                'Photo: https://example.test/photo.png',
            ]),
        ], [
            'color_lookup' => [],
        ]);

        $this->assertSame('Gold', $this->valueForHeader($template, $row, '袖子绣线颜色'));
        $this->assertSame('全彩', $this->valueForHeader($template, $row, '全彩/轮廓'));
        $this->assertSame('https://example.test/photo.png', $this->valueForHeader($template, $row, '胸部图片'));
        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '胸部位置'));
    }

    public function test_option_name_rules_build_chest_state_font_and_color_fields_before_sku_fallback()
    {
        $template = new CtcxTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $key = $optionName . '|' . $optionValue;

                $images = [
                    'Choose State Options|California' => 'https://example.test/california.png',
                    '2nd Line Font|F4' => 'https://example.test/font-f4.png',
                    '3rd Line Font|F5' => 'https://example.test/font-f5.png',
                ];

                return $images[$key] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-3',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK0743-CX-001',
            'cleaned_sku' => 'CS-QK0743-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'Choose State Options: California',
                'School Initials: CHC',
                'Color for Initials: White, Yellow, Red',
                'Add 2nd Line Text: Chestnut Hill College',
                '2nd Line Font: F4',
                'Color for 2nd Line: Silver',
                'Add 3rd Line Text: Early childhood education',
                '3rd Line Font: F5',
                'Color for 3rd Line: Gold',
                'Text Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'White' => '白色',
                'Yellow' => '黄色',
                'Red' => '红色',
                'Silver' => '银色',
                'Gold' => '金色',
            ],
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame("第一行：CHC\n第二行：Chestnut Hill College\n第三行：Early childhood education", $this->valueForHeader($template, $row, '胸部信息'));
        $this->assertSame("第一行：白色, 黄色, 红色\n第二行：银色\n第三行：金色", $this->valueForHeader($template, $row, '胸部信息颜色'));
        $this->assertSame('https://example.test/california.png', $this->valueForHeader($template, $row, '胸部图片'));
        $this->assertSame("2nd Line Font：https://example.test/font-f4.png\n3rd Line Font：https://example.test/font-f5.png", $this->valueForHeader($template, $row, '字体信息'));
        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '胸部位置'));
    }

    public function test_option_name_rules_ignore_add_second_and_third_line_prompt_and_stack_line_colors_to_chest_info_color()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-4',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK9999-CX-001',
            'cleaned_sku' => 'CS-QK9999-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'School Initials: ABC',
                'Add 2nd line & 3rd line text: Yes',
                'Add 2nd Line Text: Real second line',
                'Color for 2nd Line: Silver',
                'Add 3rd Line Text: Real third line',
                'Color for 3rd Line: Gold',
            ]),
        ], [
            'color_lookup' => [
                'Silver' => '银色',
                'Gold' => '金色',
            ],
        ]);

        $this->assertSame("第一行：ABC\n第二行：Real second line\n第三行：Real third line", $this->valueForHeader($template, $row, '胸部信息'));
        $this->assertSame("第二行：银色\n第三行：金色", $this->valueForHeader($template, $row, '胸部信息颜色'));
    }

    public function test_option_name_rules_omit_empty_chest_info_lines()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-5',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK9999-CX-002',
            'cleaned_sku' => 'CS-QK9999-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'Add 2nd Line Text: Real second line',
                'Color for 3rd Line: Gold',
            ]),
        ], [
            'color_lookup' => [
                'Gold' => '金色',
            ],
        ]);

        $this->assertSame('第二行：Real second line', $this->valueForHeader($template, $row, '胸部信息'));
        $this->assertSame('第三行：金色', $this->valueForHeader($template, $row, '胸部信息颜色'));
    }

    public function test_line_color_options_are_case_insensitive_and_do_not_fill_sleeve_thread_color()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK9999-CX-003',
            'cleaned_sku' => 'CS-QK9999-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'Color For Initials: White',
                'Color for 2nd line: Silver',
                'Color for 3rd Line: Gold',
            ]),
        ], [
            'color_lookup' => [
                'White' => '白色',
                'Silver' => '银色',
                'Gold' => '金色',
            ],
        ]);

        $this->assertSame("第一行：白色\n第二行：银色\n第三行：金色", $this->valueForHeader($template, $row, '胸部信息颜色'));
        $this->assertSame('', $this->valueForHeader($template, $row, '袖子绣线颜色'));
    }

    public function test_thread_color_line_options_map_to_chest_info_color_not_sleeve_thread_color()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6B',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK9999-CX-004',
            'cleaned_sku' => 'CS-QK9999-CX',
            'product_specs' => implode("\n", [
                'Variants: Crewneck',
                'Adult Unisex Size: XL',
                'Choose Crewneck Color: Gray',
                'School Initials: OWU',
                'Thread Color for Initials(Multiple options, 3 max): White, Black, Red',
                'Add 2nd line & 3rd line text: 2nd (+$1.99)',
                'Add 2nd Line Text: Ohio Wesleyan University',
                '2nd Line Font: F5',
                'Thread Color for 2nd Line: Red',
            ]),
        ], [
            'color_lookup' => [
                'White' => 'White CN',
                'Black' => 'Black CN',
                'Red' => 'Red CN',
            ],
        ]);

        $this->assertStringContainsString('White CN, Black CN, Red CN', $row[20]);
        $this->assertStringContainsString('Red CN', $row[20]);
        $this->assertSame('', $row[18]);
    }

    public function test_defaults_chest_position_to_center_when_specs_and_sku_have_no_position()
    {
        $template = new CtcxTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-7',
            'product_image' => '',
            'style' => '',
            'color' => '',
            'size' => 'M',
            'quantity' => 1,
            'sku' => 'ABC-CS-QK9999-CX-004',
            'cleaned_sku' => 'CS-QK9999-CX',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'School Initials: ABC',
            ]),
        ], []);

        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '胸部位置'));
    }

    public function test_upload_your_icon_choice_uses_the_following_side_specific_upload_value()
    {
        $template = new CtcxTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $images = [
                    'Upload Your Icon on Left Sleeve|https://example.test/left.png' => 'storage/app/private/sku-options-image/left.png',
                    'Upload Your Icon on Right Sleeve|https://example.test/right.png' => 'storage/app/private/sku-options-image/right.png',
                ];

                return $images[$optionName . '|' . $optionValue] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-UPLOAD-ICON',
            'sku' => 'CS-UPLOAD-ICON',
            'cleaned_sku' => 'CS-UPLOAD-ICON',
            'product_specs' => implode("\n", [
                'Color: White',
                'Size: M',
                'Material: Cotton',
                'Choose Icon on Left Sleeve: Upload Your Icon',
                'Upload Your Icon on Left Sleeve: https://example.test/left.png',
                'Choose Icon on Right Sleeve: Upload Your Icon',
                'Upload Your Icon on Right Sleeve: https://example.test/right.png',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('storage/app/private/sku-options-image/left.png', $row[10]);
        $this->assertSame('storage/app/private/sku-options-image/right.png', $row[15]);
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
