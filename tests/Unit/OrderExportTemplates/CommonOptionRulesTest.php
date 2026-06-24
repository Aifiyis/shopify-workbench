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
        $this->assertSame("Left Sleeve Icon Name 1：https://example.test/heart.png\nIcon Name #2：https://example.test/star.png", $this->valueForHeader($template, $row, '左袖图标'));
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

    public function test_heat_transfer_name_line_values_move_to_chest_info_when_defaulted_to_center_position()
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

        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '烫画位置'));
        $this->assertSame("Name 1：Alice\nName #2：Bob", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '左袖信息'));
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

    public function test_common_greeting_card_and_gift_bag_ignore_no_thanks_values()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6B',
            'sku' => 'TEXT-1B',
            'cleaned_sku' => 'TEXT-1B',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Greeting Card: No. Thanks(+$0.00)',
                'Gift Bag: No thanks(+$0.00)',
            ]),
        ], []);

        $this->assertSame('', $this->valueForHeader($template, $row, '贺卡/礼品'));
    }

    public function test_common_greeting_card_and_gift_bag_use_image_when_available_and_value_when_missing()
    {
        $template = new HeatTransferClothingTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                if ($optionName === 'Greeting Card' && $optionValue === 'Birthday') {
                    return 'https://example.test/card.png';
                }

                return '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6C',
            'sku' => 'HEAT-GIFT-1',
            'cleaned_sku' => 'HEAT-GIFT-1',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Greeting Card: Birthday',
                'Gift Bag: Red Bag',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertSame('https://example.test/card.png', $this->valueForHeader($template, $row, '贺卡'));
        $this->assertSame('Red Bag', $this->valueForHeader($template, $row, '礼品袋'));
    }

    public function test_common_icon_and_gift_rules_filter_placeholder_values_before_and_after_image_resolution()
    {
        $template = new HeatTransferClothingTemplate();
        $resolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                $images = [
                    'Left Sleeve Icon Name 1|Heart' => 'https://example.test/heart.png',
                    'Greeting Card|Birthday' => 'https://example.test/card.png',
                    'Gift Bag|Basic Black Gift Bag' => 'https://example.test/bag.png',
                    'Greeting Card|No' => 'https://example.test/no-card.png',
                    'Gift Bag|Yes' => 'https://example.test/yes-bag.png',
                ];

                return $images[$optionName . '|' . $optionValue] ?? '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6D',
            'sku' => 'HEAT-GIFT-2',
            'cleaned_sku' => 'HEAT-GIFT-2',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Icon Name 1: Heart, Yes, No, No Thank, No thanks, No. Thanks',
                'Greeting Card: Birthday, No, No Thanks',
                'Gift Bag: Basic Black Gift Bag, Yes, No. Thanks',
            ]),
        ], [
            'sku_option_image_resolver' => $resolver,
        ]);

        $this->assertStringContainsString('https://example.test/heart.png', $row[10]);
        $this->assertStringNotContainsString('Yes', $row[10]);
        $this->assertStringNotContainsString('No', $row[10]);
        $this->assertSame('https://example.test/card.png', $row[52]);
        $this->assertSame('https://example.test/bag.png', $row[53]);
    }

    public function test_common_name_text_rules_ignore_placeholder_switch_values()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-6E',
            'sku' => 'TEXT-1E',
            'cleaned_sku' => 'TEXT-1E',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Add Icon/Text On Left Sleeve?: Yes',
                'Add Icon/Text On Right Sleeve?: No',
            ]),
        ], []);

        $this->assertSame('', $this->valueForHeader($template, $row, '左袖信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '右袖信息'));
    }

    public function test_product_specs_position_wins_over_fixed_sku_placement()
    {
        $template = new HeatTransferClothingTemplate();
        $placementResolver = new class {
            public function resolve($cleanedSku, $website = '')
            {
                return '左胸口';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-7',
            'sku' => 'HEAT-5',
            'cleaned_sku' => 'HEAT-5',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Print Position: Back',
                'Name: Alice',
            ]),
        ], [
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame('背部中央', $this->valueForHeader($template, $row, '烫画位置'));
        $this->assertSame('', $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_heat_transfer_fixed_placement_appends_custom_specs_to_chest_info()
    {
        $template = new HeatTransferClothingTemplate();
        $placementResolver = new class {
            public function resolve($cleanedSku, $website = '')
            {
                return $cleanedSku === 'CS-QK3312-TH' ? '左胸口' : '';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8',
            'sku' => 'RAW-HEAT-1',
            'cleaned_sku' => 'CS-QK3312-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Name: Alice',
                'Year: 1978',
                'Photo: https://example.test/design.png',
            ]),
        ], [
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame('左胸口', $this->valueForHeader($template, $row, '烫画位置'));
        $this->assertSame("Name：Alice\nYear：1978\nPhoto：https://example.test/design.png", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('https://example.test/design.png', $this->valueForHeader($template, $row, '设计稿'));
    }

    public function test_heat_transfer_defaults_position_to_center_when_specs_and_sku_have_no_position()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8B',
            'sku' => 'RAW-HEAT-2',
            'cleaned_sku' => 'CS-NOT-MAPPED',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Custom Text: Hello',
            ]),
        ], []);

        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '烫画位置'));
    }

    public function test_heat_transfer_choose_recipient_maps_to_chest_info()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8C',
            'sku' => 'RAW-HEAT-3',
            'cleaned_sku' => 'CS-NOT-MAPPED',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Choose Recipient: Dad',
            ]),
        ], []);

        $this->assertSame('Dad', $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_heat_transfer_seagull_sku_maps_chest_info_color_and_clears_left_sleeve_info()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8D',
            'sku' => 'RAW-HEAT-4',
            'cleaned_sku' => 'CS-MYX8437-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Custom Text: Beach Crew',
                'Name #1: Lily',
                'Name #2: Max',
                'Text Color: Blue',
            ]),
        ], [
            'color_lookup' => [
                'Blue' => '蓝色',
            ],
        ]);

        $this->assertSame("Beach Crew\nName #1：Lily\nName #2：Max", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('蓝色', $this->valueForHeader($template, $row, '胸口信息颜色'));
        $this->assertSame('', $this->valueForHeader($template, $row, '左袖信息'));
    }

    public function test_heat_transfer_center_position_moves_name_lines_to_chest_info()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8D2',
            'sku' => 'RAW-HEAT-4B',
            'cleaned_sku' => 'CS-CENTER-NAMES-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Placement: Center',
                'Name #1: Lily',
                'Name #2: Max',
            ]),
        ], []);

        $this->assertSame('胸部中央', $this->valueForHeader($template, $row, '烫画位置'));
        $this->assertSame("Name #1：Lily\nName #2：Max", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '左袖信息'));
    }

    public function test_heat_transfer_qk3543_maps_uploaded_image_to_chest_image_and_sets_black_white_style()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8D3',
            'sku' => 'RAW-CS-QK3543-TH',
            'cleaned_sku' => 'CS-QK3543-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Add Your Image: https://example.test/photo.jpg',
            ]),
        ], []);

        $this->assertSame('https://example.test/photo.jpg', $this->valueForHeader($template, $row, '胸口图片1'));
        $this->assertSame('黑白', $this->valueForHeader($template, $row, '照片艺术风格（黑白复古/原图）'));
    }

    public function test_heat_transfer_regiment_sku_builds_chest_info_from_custom_fields()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-8E',
            'sku' => 'RAW-HEAT-5',
            'cleaned_sku' => 'CS-JZF6799-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Custom Name: Alice',
                'Custom Regiment: 7th Cavalry',
                'Custom Dates: 1990-2026',
            ]),
        ], []);

        $this->assertSame("姓名：Alice\n军团：7th Cavalry\n年份：1990-2026", $this->valueForHeader($template, $row, '胸口信息'));
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

    public function test_common_sleeve_position_rules_find_position_columns_after_icon_columns()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-9A',
            'sku' => 'HEAT-SLEEVE-POSITION',
            'cleaned_sku' => 'HEAT-SLEEVE-POSITION',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Text: Lily',
                'Right Sleeve Icon: Star',
            ]),
        ], []);

        $this->assertSame('左袖', $row[13]);
        $this->assertSame('右袖', $row[18]);
    }

    public function test_common_sleeve_position_rules_work_with_different_template_column_layout()
    {
        $template = new BigNumberHeatTransferHoodieTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-9B',
            'sku' => 'BIG-SLEEVE-POSITION',
            'cleaned_sku' => 'BIG-SLEEVE-POSITION',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Left Sleeve Icon: Heart',
                'Right Sleeve Text: Bob',
            ]),
        ], []);

        $this->assertSame('左袖', $row[11]);
        $this->assertSame('右袖', $row[15]);
    }

    public function test_text_embroidery_qk5914_builds_chest_info_from_nickname_and_year_values()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-9',
            'sku' => 'RAW-CS-QK5914-CX',
            'cleaned_sku' => 'CS-QK5914-CX',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Nickname: Champ',
                'EST Year: 2024',
                'Custom Year: 1998',
            ]),
        ], []);

        $this->assertSame("Champ\n2024\n1998", $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_heat_transfer_seagull_count_builds_chest_info_from_nickname_and_name_values()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-10',
            'sku' => 'RAW-SEAGULLS',
            'cleaned_sku' => 'CS-SEAGULLS-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Nickname: Beach Crew',
                'Number Of Seagulls: 2',
                'Name #1: Lily',
                'Name #2: Max',
            ]),
        ], []);

        $this->assertSame("Beach Crew\n海鸥身上小孩名：\nLily\nMax", $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_heat_transfer_kid_count_builds_chest_info_from_nickname_and_name_values()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-11',
            'sku' => 'RAW-KIDS',
            'cleaned_sku' => 'CS-KIDS-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Nickname: Mom',
                'Number Of Children: 3',
                'Name #1: Ava',
                'Name #2: Ben',
                'Name #3: Cora',
            ]),
        ], []);

        $this->assertSame("Mom\n小孩名：\nAva\nBen\nCora", $this->valueForHeader($template, $row, '胸口信息'));
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
