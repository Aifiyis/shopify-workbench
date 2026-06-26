<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\NeckHoleEmbroideryTemplate;
use Tests\TestCase;

class NeckHoleEmbroideryTemplateTest extends TestCase
{
    public function test_collar_embroidery_sets_collar_info_and_defaults_position_to_left_collar()
    {
        $template = new NeckHoleEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-1',
            'sku' => 'NECK-1',
            'cleaned_sku' => 'NECK-1',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Collar Embroidery: LUCKY',
            ]),
        ], []);

        $this->assertSame('LUCKY', $this->valueForHeader($template, $row, '领口信息'));
        $this->assertSame('左领口', $this->valueForHeader($template, $row, '刺绣位置'));
    }

    public function test_no_thanks_icon_image_is_not_written_to_sleeve_symbol()
    {
        $template = new NeckHoleEmbroideryTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\cs-qk4010-cx_no-thanks.jpg';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-2',
            'sku' => 'CS-QK4010-CX',
            'cleaned_sku' => 'CS-QK4010-CX',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Icon: No Thanks',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('', $this->valueForHeader($template, $row, '左袖符号'));
    }

    public function test_qk0007_appends_college_team_logo_and_maps_enter_name_text_to_sleeve_info()
    {
        $template = new NeckHoleEmbroideryTemplate();
        $skuImageResolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $images = [
                    'Heart' => 'https://example.test/heart.png',
                    'Star' => 'https://example.test/star.png',
                ];

                return $images[$optionValue] ?? '';
            }
        };
        $logoResolver = new class {
            public function resolve($teamName)
            {
                $images = [
                    'Air Force Falcons' => 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\lookups\\logo\\air-force-falcons.png',
                    'Alabama Crimson Tide' => 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\lookups\\logo\\alabama-crimson-tide.png',
                ];

                return $images[$teamName] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-3',
            'sku' => 'RAW-CS-QK0007-CX',
            'cleaned_sku' => 'CS-QK0007-CX',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Embroidery Icon #Left Sleeve: Heart, Choose A College Team Logo(+$3.99)',
                'Choose Your College Team Logo On Left Sleeve: Air Force Falcons',
                'Choose Embroidery Icon #Right Sleeve: Star, Add Your Name/Text (+$3.99), Choose A College Team Logo(+$3.99)',
                'Choose Your College Team Logo On Right Sleeve: Alabama Crimson Tide',
                'Enter Name/Text (Right Sleeve): Cadet Hoenig',
            ]),
        ], [
            'sku_option_image_resolver' => $skuImageResolver,
            'logo_lookup_resolver' => $logoResolver,
        ]);

        $this->assertSame("https://example.test/heart.png\nD:\\workspace\\shopify-workbench\\storage\\app\\private\\lookups\\logo\\air-force-falcons.png", $row[10]);
        $this->assertSame('左袖', $row[12]);
        $this->assertSame('Cadet Hoenig', $row[13]);
        $this->assertSame("https://example.test/star.png\nD:\\workspace\\shopify-workbench\\storage\\app\\private\\lookups\\logo\\alabama-crimson-tide.png", $row[14]);
        $this->assertSame('右袖', $row[17]);
        $this->assertStringNotContainsString('College Team Logo', $row[10]);
        $this->assertStringNotContainsString('Add Your Name/Text', $row[14]);
    }

    public function test_phrase_and_upper_lower_stitch_colors_map_to_collar_columns()
    {
        $template = new NeckHoleEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-4',
            'sku' => 'NECK-4',
            'cleaned_sku' => 'NECK-4',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Phrase: Lucky Day',
                'Upper Stitch Color: White',
                'Lower Stitch Color: Black',
            ]),
        ], [
            'color_lookup' => [
                'White' => '白色',
                'Black' => '黑色',
            ],
        ]);

        $this->assertSame('Lucky Day', $this->valueForHeader($template, $row, '领口信息'));
        $this->assertSame("上线颜色：白色\n下线颜色：黑色", $this->valueForHeader($template, $row, '领口文本颜色'));
    }

    public function test_qk4010_maps_school_initials_and_custom_text_to_collar_info()
    {
        $template = new NeckHoleEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-5',
            'sku' => 'RAW-CS-QK4010-CX',
            'cleaned_sku' => 'CS-QK4010-CX',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'School Initials: OWU',
                'Custom Text Unter The School Initials: Ohio Wesleyan University',
            ]),
        ], []);

        $this->assertSame("第一行：OWU\n第二行：Ohio Wesleyan University", $this->valueForHeader($template, $row, '领口信息'));
    }

    public function test_qk0007_maps_heart_text_names_with_heart_note_and_uploaded_left_logo()
    {
        $template = new NeckHoleEmbroideryTemplate();
        $skuImageResolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                if ($optionName === 'Upload Your Photo/Logo (Left Sleeve)' && $optionValue === 'custom-logo.png') {
                    return 'https://example.test/custom-logo.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6',
            'sku' => 'RAW-CS-QK0007-CX',
            'cleaned_sku' => 'CS-QK0007-CX',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Heart text: Left Love',
                'Right Heart text: Right Love',
                'Choose Embroidery Icon #Left Sleeve: Names with Heart',
                'Upload Your Photo/Logo (Left Sleeve): custom-logo.png',
            ]),
        ], [
            'sku_option_image_resolver' => $skuImageResolver,
        ]);

        $this->assertSame('Left Love', $row[9]);
        $this->assertStringContainsString('https://example.test/custom-logo.png', $row[10]);
        $this->assertSame('Right Love', $row[13]);
        $this->assertSame("\u{6587}\u{672C}\u{5728}\u{7231}\u{5FC3}\u{91CC}", $row[15]);
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
