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

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
