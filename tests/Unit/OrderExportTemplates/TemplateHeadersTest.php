<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\CtcxTemplate;
use App\Services\OrderExportTemplates\BigNumberHeatTransferHoodieTemplate;
use App\Services\OrderExportTemplates\HeatTransferClothingTemplate;
use App\Services\OrderExportTemplates\NeckHoleEmbroideryTemplate;
use App\Services\OrderExportTemplates\PersonOutlineColorTemplate;
use App\Services\OrderExportTemplates\PetOutlineColorTemplate;
use App\Services\OrderExportTemplates\StyleImageHeatTransferTemplate;
use App\Services\OrderExportTemplates\TextEmbroideryTemplate;
use Tests\TestCase;

class TemplateHeadersTest extends TestCase
{
    public function test_first_batch_templates_use_demo_workbook_headers()
    {
        $expectations = [
            [new NeckHoleEmbroideryTemplate(), 26, '导表日期', '备注'],
            [new CtcxTemplate(), 32, '导表日期', '设计稿'],
            [new PetOutlineColorTemplate(), 39, '导表日期', '设计稿'],
            [new BigNumberHeatTransferHoodieTemplate(), 25, '导表日期', '贺卡/包装'],
            [new HeatTransferClothingTemplate(), 58, '导表日期', '备注'],
            [new StyleImageHeatTransferTemplate(), 20, '导表日期', '贺卡/包装'],
            [new PersonOutlineColorTemplate(), 48, '导表日期', '设计稿'],
            [new TextEmbroideryTemplate(), 31, '导表日期', '贺卡/礼品'],
        ];

        foreach ($expectations as $expectation) {
            [$template, $count, $firstHeader, $previousLastHeader] = $expectation;
            $headers = $template->headers();

            $this->assertCount($count, $headers, $template->label());
            $this->assertSame($firstHeader, $headers[0], $template->label());
            $this->assertSame($previousLastHeader, $headers[count($headers) - 4], $template->label());
            $this->assertSame('产品规格', $headers[count($headers) - 3], $template->label());
            $this->assertSame('sku', $headers[count($headers) - 2], $template->label());
            $this->assertSame('cleaned_sku', $headers[count($headers) - 1], $template->label());
        }
    }

    public function test_templates_append_source_product_specs_for_review()
    {
        $templates = [
            new NeckHoleEmbroideryTemplate(),
            new CtcxTemplate(),
            new PetOutlineColorTemplate(),
            new BigNumberHeatTransferHoodieTemplate(),
            new HeatTransferClothingTemplate(),
            new StyleImageHeatTransferTemplate(),
            new PersonOutlineColorTemplate(),
            new TextEmbroideryTemplate(),
        ];
        $productSpecs = "Color: White\nSize: M\nPhoto: https://example.test/photo.png";

        foreach ($templates as $template) {
            $row = $template->mapRow([
                'filename_key' => '0601',
                'order_id' => 'ORDER-1',
                'sku' => 'RAW-SKU-1',
                'cleaned_sku' => 'CLEAN-SKU-1',
                'product_specs' => $productSpecs,
            ], []);
            $headers = $template->headers();

            $this->assertSame('产品规格', $headers[count($headers) - 3], $template->label());
            $this->assertSame('sku', $headers[count($headers) - 2], $template->label());
            $this->assertSame('cleaned_sku', $headers[count($headers) - 1], $template->label());
            $this->assertSame($productSpecs, $row[count($row) - 3], $template->label());
            $this->assertSame('RAW-SKU-1', $row[count($row) - 2], $template->label());
            $this->assertSame('CLEAN-SKU-1', $row[count($row) - 1], $template->label());
        }
    }
}
