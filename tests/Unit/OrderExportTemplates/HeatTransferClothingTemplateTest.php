<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\HeatTransferClothingTemplate;
use Tests\TestCase;

class HeatTransferClothingTemplateTest extends TestCase
{
    public function test_qk3311_maps_sleeve_design_image_and_routes_pet_name_to_back_for_back_center_position()
    {
        $template = new HeatTransferClothingTemplate();
        $imageResolver = new class {
            public function resolve($cleanedSku, $optionName, $optionValue)
            {
                if ($cleanedSku === 'CS-QK3311-TH'
                    && $optionName === 'Add Paws and Hands Line Art Design on Sleeve'
                    && $optionValue === 'Paw Line Art') {
                    return 'D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-line-art.png';
                }

                return '';
            }
        };
        $placementResolver = new class {
            public function resolve($cleanedSku, $website = '')
            {
                return '背部中央';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-HEAT-QK3311-1',
            'sku' => 'RAW-CS-QK3311-TH',
            'cleaned_sku' => 'CS-QK3311-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Add Paws and Hands Line Art Design on Sleeve: Paw Line Art',
                'Enter Text On Left Sleeve: No thanks',
                'Pet Name: Fido',
            ]),
        ], [
            'sku_option_image_resolver' => $imageResolver,
            'sku_placement_resolver' => $placementResolver,
        ]);

        $this->assertSame('D:\\workspace\\shopify-workbench\\storage\\app\\private\\sku-options-image\\paw-line-art.png', $this->valueForHeader($template, $row, '左袖图标'));
        $this->assertSame('', $this->valueForHeader($template, $row, '左袖信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('Fido', $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_qk3311_maps_left_sleeve_text_and_routes_pet_name_to_chest_when_not_back_center()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-HEAT-QK3311-2',
            'sku' => 'RAW-CS-QK3311-TH',
            'cleaned_sku' => 'CS-QK3311-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Add Paws and Hands Line Art Design on Sleeve: No',
                'Enter Text On Left Sleeve: Luna',
                'Pet Name: Buddy',
            ]),
        ], []);

        $this->assertSame('', $this->valueForHeader($template, $row, '左袖图标'));
        $this->assertSame('Luna', $this->valueForHeader($template, $row, '左袖信息'));
        $this->assertSame('Buddy', $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame('', $this->valueForHeader($template, $row, '后背信息'));
    }

    public function test_myx7637_maps_family_name_to_left_sleeve_and_kids_name_to_chest_info()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-HEAT-MYX7637',
            'sku' => 'RAW-CS-MYX7637-TH',
            'cleaned_sku' => 'CS-MYX7637-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Nickname: Crew',
                'Family Name: Smith',
                'Kids Name: Ava',
            ]),
        ], []);

        $this->assertSame('Smith', $this->valueForHeader($template, $row, '左袖信息'));
        $this->assertSame("Crew\n小孩名：Ava", $this->valueForHeader($template, $row, '胸口信息'));
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
