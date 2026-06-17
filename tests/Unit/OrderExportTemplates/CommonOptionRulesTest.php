<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\HeatTransferClothingTemplate;
use App\Services\OrderExportTemplates\BigNumberHeatTransferHoodieTemplate;
use App\Services\OrderExportTemplates\TextEmbroideryTemplate;
use Tests\TestCase;

class CommonOptionRulesTest extends TestCase
{
    public function test_common_name_icon_color_and_custom_chest_title_rules_map_to_template_columns()
    {
        $template = new HeatTransferClothingTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $images = [
                    'Left Sleeve Icon Name 1|Heart' => 'https://example.test/heart.png',
                    'Icon Name #2|Star' => 'https://example.test/star.png',
                    'Right Sleeve Icon|Crown' => 'https://example.test/crown.png',
                ];

                return $images[$optionName . '|' . $optionValue] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-1',
            'sku' => 'HEAT-1',
            'cleaned_sku' => 'HEAT-1',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Name: Alice',
                'Right Sleeve Text: Bob',
                'Collar Text: Collar Words',
                'Add Left Sleeve Text: Ignored',
                'Left Sleeve Icon Name 1: Heart',
                'Icon Name #2: Star',
                'Right Sleeve Icon: Crown',
                'Left Sleeve Color: Red',
                'Right Sleeve Color: Blue',
                'Collar Color: Gold',
                'Thread Color: Black',
                'Chest Color: White',
                'Custom Chest Title: BIG TITLE',
            ]),
        ], [
            'color_lookup' => [
                'Red' => '红色',
                'Blue' => '蓝色',
                'Gold' => '金色',
                'Black' => '黑色',
                'White' => '白色',
            ],
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('Alice', $this->valueForHeader($template, $row, '左袖信息'));
        $this->assertSame('Bob', $this->valueForHeader($template, $row, '右袖信息'));
        $this->assertSame("第一行：Left Sleeve Icon Name 1：https://example.test/heart.png\n第二行：Icon Name #2：https://example.test/star.png", $this->valueForHeader($template, $row, '左袖图标'));
        $this->assertSame('https://example.test/crown.png', $this->valueForHeader($template, $row, '右袖图标'));
        $this->assertSame('红色', $this->valueForHeader($template, $row, '左袖线色'));
        $this->assertSame('蓝色', $this->valueForHeader($template, $row, '右袖线色'));
        $this->assertSame('白色', $this->valueForHeader($template, $row, '胸口信息颜色'));
        $this->assertSame('BIG TITLE', $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_common_thread_color_maps_to_sleeve_color_column_when_present()
    {
        $template = new BigNumberHeatTransferHoodieTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-2',
            'sku' => 'BIG-1',
            'cleaned_sku' => 'BIG-1',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Thread Color: Black',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
            ],
        ]);

        $this->assertSame('黑色', $this->valueForHeader($template, $row, '袖子线色'));
    }

    public function test_common_rules_use_first_color_from_first_three_specs_and_allow_add_text()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-3',
            'sku' => 'HEAT-2',
            'cleaned_sku' => 'HEAT-2',
            'color' => 'Fallback Color',
            'product_specs' => implode("\n", [
                'Style: Hoodie',
                'Garment Color: Navy',
                'Size: M',
                'Add Left Sleeve Text: Added Alice',
            ]),
        ], [
            'color_lookup' => [
                'Navy' => '藏青色',
            ],
        ]);

        $this->assertSame('藏青色', $this->valueForHeader($template, $row, '衣服颜色'));
        $this->assertSame('Added Alice', $this->valueForHeader($template, $row, '左袖信息'));
    }

    public function test_common_icon_and_pattern_rules_resolve_multiple_images_and_filter_placeholder_values()
    {
        $template = new HeatTransferClothingTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $images = [
                    'Left Sleeve Pattern|Heart' => 'https://example.test/heart.png',
                    'Left Sleeve Pattern|Star' => 'https://example.test/star.png',
                    'Right Sleeve Icon|Crown' => 'https://example.test/crown.png',
                ];

                return $images[$optionName . '|' . $optionValue] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-4',
            'sku' => 'HEAT-3',
            'cleaned_sku' => 'HEAT-3',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Pattern: Heart, Upload Photo, Add Name, Choose Logo, Yes, No Thank You, Star',
                'Right Sleeve Icon: Crown',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame("https://example.test/heart.png\nhttps://example.test/star.png", $this->valueForHeader($template, $row, '左袖图标'));
        $this->assertSame('https://example.test/crown.png', $this->valueForHeader($template, $row, '右袖图标'));
    }

    public function test_common_name_line_values_default_to_left_sleeve_info_when_no_sleeve_options_exist()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-5',
            'sku' => 'HEAT-4',
            'cleaned_sku' => 'HEAT-4',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Name 1: Alice',
                'Name #2: Bob',
            ]),
        ], []);

        $this->assertSame("第一行：Name 1：Alice\n第二行：Name #2：Bob", $this->valueForHeader($template, $row, '左袖信息'));
    }

    public function test_common_greeting_card_gift_bag_and_nickname_rules()
    {
        $template = new TextEmbroideryTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $images = [
                    'Greeting Card|Birthday' => 'https://example.test/card.png',
                    'Gift Bag|Red Bag' => 'https://example.test/bag.png',
                ];

                return $images[$optionName . '|' . $optionValue] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6',
            'sku' => 'TEXT-1',
            'cleaned_sku' => 'TEXT-1',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Nickname: Ace',
                'Greeting Card: Birthday',
                'Gift Bag: Red Bag',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('Ace', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame("https://example.test/card.png\nhttps://example.test/bag.png", $this->valueForHeader($template, $row, '贺卡/礼品'));
    }

    public function test_text_embroidery_print_color_maps_to_sleeve_thread_color()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-7',
            'sku' => 'TEXT-2',
            'cleaned_sku' => 'TEXT-2',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Print Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Red' => '红色',
            ],
        ]);

        $this->assertSame('红色', $this->valueForHeader($template, $row, '袖子绣线颜色'));
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
