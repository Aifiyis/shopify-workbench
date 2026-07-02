<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\StyleImageHeatTransferTemplate;
use Tests\TestCase;

class StyleImageHeatTransferTemplateTest extends TestCase
{
    public function test_year_est_and_design_options_map_to_chest_info_and_design_style()
    {
        $template = new StyleImageHeatTransferTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                if ($cleanedSku === 'CS-STYLE-001' && $optionName === 'Choose Design Style' && $optionValue === 'Vintage') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\vintage.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-1',
            'sku' => 'CS-STYLE-001',
            'cleaned_sku' => 'CS-STYLE-001',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Year: 2024',
                'EST Date: 1998',
                'Choose Design Style: Vintage',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame("2024\n1998", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\vintage.png', $this->valueForHeader($template, $row, '设计风格'));
    }

    public function test_design_option_falls_back_to_value_when_image_is_missing()
    {
        $template = new StyleImageHeatTransferTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-2',
            'sku' => 'CS-STYLE-002',
            'cleaned_sku' => 'CS-STYLE-002',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Design Style: Retro',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('Retro', $this->valueForHeader($template, $row, '设计风格'));
    }

    public function test_recipient_routes_to_chest_and_back_for_mirror_placement_behavior()
    {
        $template = new StyleImageHeatTransferTemplate();
        $placementResolver = new class {
            public function resolveRule($cleanedSku, $website = '')
            {
                return [
                    'position' => '左胸和后背',
                    'placement_behavior' => 'mirror_chest_to_back',
                ];
            }

            public function resolve($cleanedSku, $website = '')
            {
                return '左胸和后背';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-3',
            'sku' => 'CS-MIRROR-TH',
            'cleaned_sku' => 'CS-MIRROR-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Recipient: Dad',
            ]),
        ], [
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame('Dad', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('Dad', $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_recipient_routes_only_to_chest_for_only_chest_placement_behavior()
    {
        $template = new StyleImageHeatTransferTemplate();
        $placementResolver = new class {
            public function resolveRule($cleanedSku, $website = '')
            {
                return [
                    'position' => '左胸口',
                    'placement_behavior' => 'only_chest',
                ];
            }

            public function resolve($cleanedSku, $website = '')
            {
                return '左胸口';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-4',
            'sku' => 'CS-CHEST-TH',
            'cleaned_sku' => 'CS-CHEST-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Recipient: Mom',
            ]),
        ], [
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame('Mom', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_year_and_est_values_route_to_chest_and_back_for_mirror_placement_behavior()
    {
        $template = new StyleImageHeatTransferTemplate();
        $placementResolver = new class {
            public function resolveRule($cleanedSku, $website = '')
            {
                return [
                    'placement_behavior' => 'mirror_chest_to_back',
                ];
            }

            public function resolve($cleanedSku, $website = '')
            {
                return '左胸和后背';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-4B',
            'sku' => 'CS-MIRROR-YEAR-TH',
            'cleaned_sku' => 'CS-MIRROR-YEAR-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Year: 2024',
                'EST Date: 1998',
            ]),
        ], [
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame("2024\n1998", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame("2024\n1998", $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_year_values_route_only_to_chest_for_only_chest_placement_behavior()
    {
        $template = new StyleImageHeatTransferTemplate();
        $placementResolver = new class {
            public function resolveRule($cleanedSku, $website = '')
            {
                return [
                    'placement_behavior' => 'only_chest',
                ];
            }

            public function resolve($cleanedSku, $website = '')
            {
                return '左胸口';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-4C',
            'sku' => 'CS-CHEST-YEAR-TH',
            'cleaned_sku' => 'CS-CHEST-YEAR-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Year: 2024',
            ]),
        ], [
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame('2024', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_specific_skus_set_chest_and_back_text_colors()
    {
        $template = new StyleImageHeatTransferTemplate();

        $redRow = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-5',
            'sku' => 'RAW-CS-LXJ7445-TH',
            'cleaned_sku' => 'CS-LXJ7445-TH',
            'product_specs' => "Color: Black\nSize: M\nMaterial: Cotton",
        ], []);

        $blackWhiteRow = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-6',
            'sku' => 'RAW-CS-QK6009-TH',
            'cleaned_sku' => 'CS-QK6009-TH',
            'product_specs' => "Color: Black\nSize: M\nMaterial: Cotton",
        ], []);

        $this->assertSame('红色', $this->valueForHeader($template, $redRow, '胸口文本颜色'));
        $this->assertSame('红色', $this->valueForHeader($template, $redRow, '后背文本颜色'));
        $this->assertSame('黑色', $this->valueForHeader($template, $blackWhiteRow, '胸口文本颜色'));
        $this->assertSame('白色', $this->valueForHeader($template, $blackWhiteRow, '后背文本颜色'));
    }

    public function test_back_and_year_color_options_append_translated_values_to_back_info()
    {
        $template = new StyleImageHeatTransferTemplate();
        $translator = new class {
            public $calls = [];

            public function translate($value)
            {
                $this->calls[] = $value;

                return '酒红色（翻译原值：' . $value . '）';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-7',
            'sku' => 'CS-STYLE-007',
            'cleaned_sku' => 'CS-STYLE-007',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Recipient: Dad',
                'Back Color: Red',
                'Year Color: Burgundy',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
                'Red' => '红色',
            ],
            'color_translation_resolver' => $translator,
            'sku_placement_resolver' => new class {
                public function resolveRule($cleanedSku, $website = '')
                {
                    return [
                        'placement_behavior' => 'mirror_chest_to_back',
                    ];
                }

                public function resolve($cleanedSku, $website = '')
                {
                    return '左胸和后背';
                }
            },
        ]);

        $this->assertSame("Dad\n红色\n酒红色（翻译原值：Burgundy）", $this->valueForHeader($template, $row, '后背信息'));
        $this->assertSame(['Burgundy'], $translator->calls);
    }

    public function test_multiple_back_color_values_use_lookup_and_fallback_translation()
    {
        $template = new StyleImageHeatTransferTemplate();
        $translator = new class {
            public function translate($value)
            {
                return '酒红色（翻译原值：' . $value . '）';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-8',
            'sku' => 'CS-STYLE-008',
            'cleaned_sku' => 'CS-STYLE-008',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Back Year Color: Red, Burgundy',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
                'Red' => '红色',
            ],
            'color_translation_resolver' => $translator,
        ]);

        $this->assertSame('红色, 酒红色（翻译原值：Burgundy）', $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_choose_style_maps_option_image_to_design_style()
    {
        $template = new StyleImageHeatTransferTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                return $optionName === 'Choose Style' && $optionValue === 'Vintage'
                    ? 'storage/app/private/sku-options-image/vintage.png'
                    : '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-CHOOSE',
            'sku' => 'CS-STYLE-CHOOSE',
            'cleaned_sku' => 'CS-STYLE-CHOOSE',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Style: Vintage',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('storage/app/private/sku-options-image/vintage.png', $row[15]);
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
