<?php

namespace Tests\Feature;

use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use App\Models\SkuMatchProductType;
use App\Services\SkuCleaningService;
use App\Services\SkuMatchProductTypeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPExcel;
use PHPExcel_IOFactory;
use Tests\TestCase;

class SkuMatchProductTypeImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = storage_path('app/testing/sku-product-type-' . uniqid());
        mkdir($this->tempDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function test_imports_json_and_excel_rows_with_craft_hierarchy()
    {
        $paths = $this->writeSourceFiles([
            [
                'original_sku' => 'JSON-QK1000-Red',
                'cleaned_sku' => 'JSON-MANUAL-CORRECTED',
                '中文名称' => '分类A',
                '上品人' => 'Alice',
            ],
        ], [
            3501 => ['IGNORE-QK1000-Red', '忽略分类', 'Ignored'],
            3502 => ['EXCEL-QK2000-Beige-kid-CX', '分类B', 'Bob'],
        ], [
            ['分类A', '礼品-定制-大橙子家', '订单A', '图画A', '采购A'],
            ['分类B', '衣服类-烫画-定制-工厂A', '订单B', '图画B', '采购B'],
        ]);

        $service = new SkuMatchProductTypeImportService(
            new SkuCleaningService(
                $paths['sku_cleaned'],
                $paths['exclude_values'],
                $paths['exclude_patterns']
            ),
            $paths['sku_cleaned'],
            $paths['workbook']
        );

        $result = $service->import();

        $this->assertSame(2, $result['sku_count']);
        $this->assertSame(2, $result['matched_sku_count']);
        $this->assertSame(0, $result['unmatched_sku_count']);

        $this->assertDatabaseHas('sku_match_product_type', [
            'original_sku' => 'JSON-QK1000-Red',
            'cleaned_sku' => 'JSON-MANUAL-CORRECTED',
            'chinese_name' => '分类A',
            'product_lister' => 'Alice',
        ]);
        $this->assertDatabaseHas('sku_match_product_type', [
            'original_sku' => 'EXCEL-QK2000-Beige-kid-CX',
            'cleaned_sku' => 'EXCEL-QK2000-CX',
            'chinese_name' => '分类B',
            'product_lister' => 'Bob',
        ]);
        $this->assertDatabaseMissing('sku_match_product_type', [
            'original_sku' => 'IGNORE-QK1000-Red',
        ]);

        $craft = ProductProcessingCraft::where('chinese_name', '分类B')->firstOrFail();
        $this->assertSame('订单B', $craft->order_processor);
        $this->assertSame('图画B', $craft->artwork_processor);
        $this->assertSame('采购B', $craft->procurement_processor);
        $this->assertNull($craft->spreadsheet_template);
        $this->assertNull($craft->spreadsheet_template_description);
        $this->assertSame('衣服类-烫画-定制-工厂A', $craft->craft->path);
        $this->assertSame('衣服类-烫画-定制', $craft->craft->parent->path);

        $sku = SkuMatchProductType::where('original_sku', 'EXCEL-QK2000-Beige-kid-CX')->firstOrFail();
        $this->assertSame('分类B', $sku->processingCraft->chinese_name);
        $this->assertSame(7, ProcessingCraftNode::count());
    }

    public function test_import_creates_unmatched_configuration_and_preserves_template_fields_on_rerun()
    {
        $paths = $this->writeSourceFiles([
            [
                'original_sku' => 'JSON-QK1000',
                'cleaned_sku' => 'JSON-QK1000',
                '中文名称' => '已匹配分类',
                '上品人' => 'Alice',
            ],
            [
                'original_sku' => 'JSON-QK2000',
                'cleaned_sku' => 'JSON-QK2000',
                '中文名称' => '未匹配分类',
                '上品人' => 'Bob',
            ],
        ], [], [
            ['已匹配分类', '礼品-定制-工厂A', '订单A', '图画A', '采购A'],
        ]);

        $service = new SkuMatchProductTypeImportService(
            new SkuCleaningService(
                $paths['sku_cleaned'],
                $paths['exclude_values'],
                $paths['exclude_patterns']
            ),
            $paths['sku_cleaned'],
            $paths['workbook']
        );

        $firstResult = $service->import();

        $this->assertSame(1, $firstResult['matched_sku_count']);
        $this->assertSame(1, $firstResult['unmatched_sku_count']);
        $this->assertDatabaseHas('product_processing_craft', [
            'chinese_name' => '未匹配分类',
            'craft_id' => null,
            'order_processor' => null,
        ]);

        $configuration = ProductProcessingCraft::where('chinese_name', '已匹配分类')->firstOrFail();
        $configuration->spreadsheet_template = 'template-a.xlsx';
        $configuration->spreadsheet_template_description = 'Manual description';
        $configuration->save();

        $secondResult = $service->import();
        $configuration->refresh();

        $this->assertSame(2, $secondResult['sku_count']);
        $this->assertSame(2, SkuMatchProductType::count());
        $this->assertSame('template-a.xlsx', $configuration->spreadsheet_template);
        $this->assertSame('Manual description', $configuration->spreadsheet_template_description);
    }

    public function test_conflicting_duplicate_sku_aborts_without_database_writes()
    {
        $paths = $this->writeSourceFiles([
            [
                'original_sku' => 'DUPLICATE-QK1000',
                'cleaned_sku' => 'DUPLICATE-QK1000',
                '中文名称' => '分类A',
                '上品人' => 'Alice',
            ],
        ], [
            3502 => ['DUPLICATE-QK1000', '分类B', 'Alice'],
        ], [
            ['分类A', '礼品-定制-工厂A', '订单A', '图画A', '采购A'],
            ['分类B', '礼品-定制-工厂B', '订单B', '图画B', '采购B'],
        ]);

        $service = new SkuMatchProductTypeImportService(
            new SkuCleaningService(
                $paths['sku_cleaned'],
                $paths['exclude_values'],
                $paths['exclude_patterns']
            ),
            $paths['sku_cleaned'],
            $paths['workbook']
        );

        try {
            $service->import();
            $this->fail('Expected conflicting SKU rows to abort the import.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('DUPLICATE-QK1000', $exception->getMessage());
        }

        $this->assertSame(0, SkuMatchProductType::count());
        $this->assertSame(0, ProductProcessingCraft::count());
        $this->assertSame(0, ProcessingCraftNode::count());
    }

    public function test_import_chunks_sku_upserts_below_sqlite_variable_limit()
    {
        $jsonRows = [];

        for ($index = 1; $index <= 200; $index++) {
            $jsonRows[] = [
                'original_sku' => 'BULK-QK' . $index,
                'cleaned_sku' => 'BULK-QK' . $index,
                '中文名称' => '批量分类',
                '上品人' => 'Alice',
            ];
        }

        $paths = $this->writeSourceFiles($jsonRows, [], [
            ['批量分类', '礼品-定制-工厂A', '订单A', '图画A', '采购A'],
        ]);
        $service = new SkuMatchProductTypeImportService(
            new SkuCleaningService(
                $paths['sku_cleaned'],
                $paths['exclude_values'],
                $paths['exclude_patterns']
            ),
            $paths['sku_cleaned'],
            $paths['workbook']
        );

        $result = $service->import();

        $this->assertSame(200, $result['sku_count']);
        $this->assertSame(200, SkuMatchProductType::count());
    }

    private function writeSourceFiles(array $jsonRows, array $allRows, array $processRows)
    {
        $skuCleanedPath = $this->tempDirectory . '/sku-cleaned.json';
        $excludeValuesPath = $this->tempDirectory . '/sku-exclude-values.json';
        $excludePatternsPath = $this->tempDirectory . '/sku-exclude-rule-patterns.json';
        $workbookPath = $this->tempDirectory . '/sku-to-product_type.xlsx';

        file_put_contents($skuCleanedPath, json_encode($jsonRows, JSON_UNESCAPED_UNICODE));
        file_put_contents($excludeValuesPath, json_encode([
            'all_exclude_values' => ['Red', 'Beige'],
        ], JSON_UNESCAPED_UNICODE));
        file_put_contents($excludePatternsPath, json_encode([
            'include_type' => [
                'fields' => [
                    ['value' => 'kid', 'regex' => '/kid/iu'],
                ],
            ],
            'equals_type' => ['fields' => []],
        ], JSON_UNESCAPED_UNICODE));

        $workbook = new PHPExcel();
        $allSheet = $workbook->getActiveSheet();
        $allSheet->setTitle('all');
        $allSheet->fromArray(['sku', '中文名称', '上品人'], null, 'A1');

        foreach ($allRows as $rowNumber => $row) {
            $allSheet->fromArray($row, null, 'A' . $rowNumber);
        }

        $processSheet = $workbook->createSheet();
        $processSheet->setTitle('新处理工艺');
        $processSheet->fromArray(
            ['中文名称', '工艺', '订单处理人', '图画处理人', '采购处理人'],
            null,
            'A1'
        );

        foreach ($processRows as $index => $row) {
            $processSheet->fromArray($row, null, 'A' . ($index + 2));
        }

        PHPExcel_IOFactory::createWriter($workbook, 'Excel2007')->save($workbookPath);

        return [
            'sku_cleaned' => $skuCleanedPath,
            'exclude_values' => $excludeValuesPath,
            'exclude_patterns' => $excludePatternsPath,
            'workbook' => $workbookPath,
        ];
    }

    private function deleteDirectory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
