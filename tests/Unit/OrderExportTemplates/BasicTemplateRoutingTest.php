<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\HeatTransferClothingTemplate;
use Tests\TestCase;

class BasicTemplateRoutingTest extends TestCase
{
    public function test_heat_transfer_template_returns_base_row_values()
    {
        $template = new HeatTransferClothingTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-1',
            'product_image' => 'https://example.test/image.png',
            'style' => 'Hoodie',
            'color' => 'White',
            'size' => 'XL',
            'quantity' => 2,
            'sku' => 'CS-QK1000',
            'cleaned_sku' => 'CS-QK1000',
            'product_specs' => "Color: White\nSize: XL\nMaterial: Cotton\nPhoto: https://example.test/design.png",
        ], []);

        $this->assertSame('0601', $this->valueForHeader($template, $row, '导表日期'));
        $this->assertSame('ORDER-1', $this->valueForHeader($template, $row, '订单号'));
        $this->assertSame('https://example.test/image.png', $this->valueForHeader($template, $row, '款式图'));
        $this->assertSame('Hoodie', $this->valueForHeader($template, $row, '款式'));
        $this->assertSame('White', $this->valueForHeader($template, $row, '衣服颜色'));
        $this->assertSame('XL', $this->valueForHeader($template, $row, '尺码'));
        $this->assertSame(2, $this->valueForHeader($template, $row, '数量'));
        $this->assertSame('https://example.test/design.png', $this->valueForHeader($template, $row, '设计稿'));
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
