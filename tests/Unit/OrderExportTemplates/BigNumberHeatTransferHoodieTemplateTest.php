<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\BigNumberHeatTransferHoodieTemplate;
use Tests\TestCase;

class BigNumberHeatTransferHoodieTemplateTest extends TestCase
{
    public function test_defaults_chest_position_to_center()
    {
        $template = new BigNumberHeatTransferHoodieTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-BIG-1',
            'sku' => 'RAW-CS-OTHER-TH',
            'cleaned_sku' => 'CS-OTHER-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
            ]),
        ], []);

        $this->assertSame('胸部中央', $row[20]);
    }

    public function test_qk3385_maps_flag_text_note_and_sleeve_positions()
    {
        $template = new BigNumberHeatTransferHoodieTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-BIG-2',
            'sku' => 'RAW-CS-QK3385-TH',
            'cleaned_sku' => 'CS-QK3385-TH',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Left Sleeve Icon: Heart',
                'Text On The Flag: Go Team',
                'Right Sleeve Icon: Star',
            ]),
        ], [
            'sku_option_image_resolver' => new class {
                public function resolve($cleanedSku, $optionName, $optionValue)
                {
                    return '';
                }
            },
        ]);

        $this->assertSame('左袖', $row[11]);
        $this->assertSame('Go Team', $row[12]);
        $this->assertSame('Star', $row[13]);
        $this->assertSame('文本在flag里', $row[14]);
        $this->assertSame('右袖', $row[15]);
        $this->assertSame('胸部中央', $row[20]);
    }
}
