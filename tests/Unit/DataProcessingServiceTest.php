<?php

namespace Tests\Unit;

use App\Services\DataProcessingService;
use App\Services\LookupService;
use App\Services\SkuCleaningService;
use App\Services\SkuPlacementResolver;
use Tests\TestCase;

class DataProcessingServiceTest extends TestCase
{
    private $dataProcessingService;
    private $lookupService;
    private $tempDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->lookupService = new LookupService();
        $this->dataProcessingService = new DataProcessingService($this->lookupService);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            $this->deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_extract_filename_key()
    {
        // Using reflection to access private method
        $reflection = new \ReflectionClass($this->dataProcessingService);
        $method = $reflection->getMethod('extractFilenameKey');
        $method->setAccessible(true);

        $result = $method->invoke($this->dataProcessingService, 'order_0601 09-0602 09.xlsx');
        $this->assertEquals('0601 09-0602 09', $result);

        $result = $method->invoke($this->dataProcessingService, 'test_file.csv');
        $this->assertEquals('file', $result);
    }

    public function test_lookup_service_parse_attributes()
    {
        $specs = "Color: Red\nSize: M\nMaterial: Cotton";

        // Test through the public method (indirectly)
        $reflection = new \ReflectionClass($this->lookupService);
        $method = $reflection->getMethod('parseAttributes');
        $method->setAccessible(true);

        $result = $method->invoke($this->lookupService, $specs);
        $this->assertEquals('Red', $result['Color']);
        $this->assertEquals('M', $result['Size']);
        $this->assertEquals('Cotton', $result['Material']);
    }

    public function test_extract_size_from_specs()
    {
        $specs = "Color: Red\nSize: Large\nMaterial: Cotton";

        $size = $this->lookupService->extractSize($specs);
        $this->assertEquals('Large', $size);
    }

    public function test_get_cell_value_falls_back_for_dispimg_formula()
    {
        $spreadsheet = new \PHPExcel();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('order_1');
        $sheet->setCellValue('N2', '=_xlfn.DISPIMG("ID_TEST_IMAGE",1)');

        $method = $this->getDataProcessingMethod('getCellValue');
        $value = $method->invoke($this->dataProcessingService, $sheet, 13, 2);

        $this->assertSame('', $value);
    }

    public function test_groups_rows_only_for_configured_templates()
    {
        $method = $this->getDataProcessingMethod('groupRowsByTemplate');

        $rows = [
            [
                'source_row' => 2,
                'chinese_name' => '彩图刺绣',
                'sku' => 'CS-QK2571-CX',
            ],
            [
                'source_row' => 3,
                'chinese_name' => '毛毯',
                'sku' => 'BLANKET-1',
            ],
            [
                'source_row' => 4,
                'chinese_name' => '人物彩图',
                'sku' => 'PERSON-1',
            ],
        ];

        $groups = $method->invoke($this->dataProcessingService, $rows);

        $this->assertArrayHasKey('ctcx', $groups);
        $this->assertArrayHasKey('person_outline_color', $groups);
        $this->assertArrayNotHasKey('毛毯', $groups);
        $this->assertCount(1, $groups['ctcx']['rows']);
        $this->assertCount(1, $groups['person_outline_color']['rows']);
    }

    public function test_image_url_detection_accepts_ymq_links_without_file_extension()
    {
        $method = $this->getDataProcessingMethod('isImageUrl');

        $this->assertTrue($method->invoke($this->dataProcessingService, 'https://image.ymqapp.com/shopify/180/option/abc123'));
        $this->assertTrue($method->invoke($this->dataProcessingService, 'https://cdn.shopify.com/s/files/example'));
        $this->assertFalse($method->invoke($this->dataProcessingService, 'https://example.test/sku-options-image/cs-qk4010-cx_no-thanks.jpg'));
        $this->assertFalse($method->invoke($this->dataProcessingService, 'not-a-url'));
    }

    public function test_no_thanks_image_reference_writes_blank_cell()
    {
        $method = $this->getDataProcessingMethod('setCellValueOrImage');
        $spreadsheet = new \PHPExcel();
        $sheet = $spreadsheet->getActiveSheet();
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];

        $method->invokeArgs($this->dataProcessingService, [
            $sheet,
            0,
            1,
            'https://example.test/sku-options-image/cs-qk4010-cx_no-thanks.jpg',
            &$imageTempFiles,
        ]);

        $this->assertSame('', $sheet->getCell('A1')->getValue());
    }

    public function test_template_symbol_gift_and_package_columns_render_images()
    {
        $method = $this->getDataProcessingMethod('shouldRenderImageColumn');
        $headers = ['订单号', '左袖符号', '右袖符号', '贺卡', '礼品袋', '贺卡/礼品', '设计风格'];

        $this->assertTrue($method->invoke($this->dataProcessingService, $headers, 1));
        $this->assertTrue($method->invoke($this->dataProcessingService, $headers, 2));
        $this->assertTrue($method->invoke($this->dataProcessingService, $headers, 3));
        $this->assertTrue($method->invoke($this->dataProcessingService, $headers, 4));
        $this->assertTrue($method->invoke($this->dataProcessingService, $headers, 5));
        $this->assertTrue($method->invoke($this->dataProcessingService, $headers, 6));
    }

    public function test_template_mixed_local_image_references_are_inserted_as_drawings()
    {
        $method = $this->getDataProcessingMethod('setTemplateCellValue');
        $spreadsheet = new \PHPExcel();
        $sheet = $spreadsheet->getActiveSheet();
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];
        $imagePath = $this->writeTinyPng($this->makeTempDirectory('template-image-column-') . '/icon.png');

        $method->invokeArgs($this->dataProcessingService, [
            $sheet,
            ['左袖符号'],
            0,
            1,
            'Team Logo：' . $imagePath,
            &$imageTempFiles,
        ]);

        $this->assertSame('Team Logo：', $sheet->getCell('A1')->getValue());
        $this->assertCount(1, $sheet->getDrawingCollection());
    }

    public function test_template_labeled_local_image_reference_with_ascii_colon_is_inserted_as_drawing()
    {
        $method = $this->getDataProcessingMethod('setTemplateCellValue');
        $spreadsheet = new \PHPExcel();
        $sheet = $spreadsheet->getActiveSheet();
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];
        $imagePath = $this->writeTinyPng($this->makeTempDirectory('template-ascii-colon-image-column-') . '/team-logo.png');

        $method->invokeArgs($this->dataProcessingService, [
            $sheet,
            ['左袖符号'],
            0,
            1,
            'Team Logo: ' . $imagePath,
            &$imageTempFiles,
        ]);

        $this->assertSame('Team Logo:', $sheet->getCell('A1')->getValue());
        $this->assertCount(1, $sheet->getDrawingCollection());
    }

    public function test_template_local_image_references_survive_excel_save_as_drawings()
    {
        $method = $this->getDataProcessingMethod('setTemplateCellValue');
        $spreadsheet = new \PHPExcel();
        $sheet = $spreadsheet->getActiveSheet();
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];
        $tempDirectory = $this->makeTempDirectory('template-saved-image-column-');
        $firstImagePath = $this->writeTinyPng($tempDirectory . '/left-icon.png');
        $secondImagePath = $this->writeTinyPng($tempDirectory . '/team-logo.png');
        $workbookPath = $tempDirectory . '/image-output.xlsx';

        $method->invokeArgs($this->dataProcessingService, [
            $sheet,
            ['左袖符号'],
            0,
            1,
            $firstImagePath . "\nTeam Logo: " . $secondImagePath,
            &$imageTempFiles,
        ]);

        $writer = new \PHPExcel_Writer_Excel2007($spreadsheet);
        $writer->save($workbookPath);

        $reloaded = \PHPExcel_IOFactory::load($workbookPath);
        $this->assertCount(2, $reloaded->getActiveSheet()->getDrawingCollection());
        $reloaded->disconnectWorksheets();
    }

    public function test_prepare_image_for_excel_normalizes_jpeg_exif_orientation()
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            $this->markTestSkipped('GD JPEG support is required for EXIF orientation normalization.');
        }

        $tempDirectory = $this->makeTempDirectory('exif-orientation-');
        $imagePath = $tempDirectory . '/rotated-source.jpg';
        $this->writeJpegWithExifOrientation($imagePath, 3, 2, 6);
        $imageTempFiles = [
            'paths' => [],
            'cache' => [],
        ];
        $method = $this->getDataProcessingMethod('prepareImageForExcel');

        $preparedPath = $method->invokeArgs($this->dataProcessingService, [
            $imagePath,
            &$imageTempFiles,
        ]);

        $this->assertNotSame($imagePath, $preparedPath);
        $this->assertFileExists($preparedPath);
        $this->assertSame([2, 3], array_slice(getimagesize($preparedPath), 0, 2));
    }

    private function writeJpegWithExifOrientation($path, $width, $height, $orientation)
    {
        $image = imagecreatetruecolor($width, $height);
        $red = imagecolorallocate($image, 255, 0, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);

        imagefill($image, 0, 0, $red);
        imagefilledrectangle($image, $width - 1, $height - 1, $width - 1, $height - 1, $blue);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        $jpeg = file_get_contents($path);
        $exif = $this->buildExifOrientationSegment($orientation);

        file_put_contents($path, substr($jpeg, 0, 2) . $exif . substr($jpeg, 2));
    }

    private function writeTinyPng($path)
    {
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        return $path;
    }

    private function buildExifOrientationSegment($orientation)
    {
        $payload = "Exif\0\0"
            . "II"
            . pack('v', 42)
            . pack('V', 8)
            . pack('v', 1)
            . pack('v', 0x0112)
            . pack('v', 3)
            . pack('V', 1)
            . pack('v', $orientation)
            . pack('v', 0)
            . pack('V', 0);

        return "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
    }

    public function test_generates_template_output_filename()
    {
        $method = $this->getDataProcessingMethod('generateTemplateOutputFilename');

        $this->assertSame(
            'order_output_人物轮廓彩图0601.xlsx',
            $method->invoke($this->dataProcessingService, '人物轮廓彩图', 'order_0601.xlsx')
        );
    }

    public function test_process_order_file_all_generates_configured_template_workbooks_only()
    {
        $tempDirectory = $this->makeTempDirectory('data-processing-e2e-');
        $skuCleanedPath = $tempDirectory . '/sku-cleaned.json';
        $excludeValuesPath = $tempDirectory . '/sku-exclude-values.json';

        file_put_contents($skuCleanedPath, json_encode([
            [
                'original_sku' => 'RAW-CTCX-1',
                'cleaned_sku' => 'CS-QK2571-CX',
                '中文名称' => '彩图刺绣',
                '工艺' => '刺绣',
                '处理人' => 'A',
                '上品人' => 'B',
            ],
            [
                'original_sku' => 'RAW-PERSON-1',
                'cleaned_sku' => 'PERSON-1',
                '中文名称' => '人物彩图',
                '工艺' => '彩图',
                '处理人' => 'A',
                '上品人' => 'B',
            ],
            [
                'original_sku' => 'RAW-BLANKET-1',
                'cleaned_sku' => 'BLANKET-1',
                '中文名称' => '毛毯',
                '工艺' => '',
                '处理人' => '',
                '上品人' => '',
            ],
            [
                'original_sku' => 'RAW-HEAT-1',
                'cleaned_sku' => 'CS-QK3312-TH',
                '中文名称' => '普通烫画衣服',
                '工艺' => '烫画',
                '处理人' => 'A',
                '上品人' => 'B',
            ],
        ], JSON_UNESCAPED_UNICODE));
        file_put_contents($excludeValuesPath, json_encode([
            'all_exclude_values' => [],
        ], JSON_UNESCAPED_UNICODE));
        $placementRulesPath = $tempDirectory . '/sku-placement-rules.json';
        file_put_contents($placementRulesPath, json_encode([
            'rules' => [
                [
                    'website' => 'broderft.com',
                    'position' => '左胸口',
                    'cleaned_skus' => ['CS-QK3312-TH'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $sourcePath = $tempDirectory . '/order_0601_e2e.xlsx';
        $this->writeSyntheticOrderWorkbook($sourcePath);

        $service = new DataProcessingService(
            $this->makeStaticLookupService(),
            new SkuCleaningService($skuCleanedPath, $excludeValuesPath),
            null,
            null,
            new SkuPlacementResolver($placementRulesPath, $tempDirectory . '/missing-sku-options-image.json')
        );

        $result = $service->processOrderFileAll($sourcePath, 'order_0601_e2e.xlsx');

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertContains('order_output_all0601_e2e.xlsx', $result['files']);
        $this->assertContains('order_output_彩图刺绣0601_e2e.xlsx', $result['files']);
        $this->assertContains('order_output_人物轮廓彩图0601_e2e.xlsx', $result['files']);
        $this->assertContains('order_output_普通烫画衣服0601_e2e.xlsx', $result['files']);
        $this->assertNotContains('order_output_毛毯0601_e2e.xlsx', $result['files']);
        $this->assertSame(3, $result['template_rows_processed']);
        $this->assertSame(1, $result['ctcx_rows_processed']);

        $this->assertTemplateWorkbookIncludesReviewColumns(
            $result['output_path'],
            'order_output_彩图刺绣0601_e2e.xlsx',
            "Color: White\nSize: M\nMaterial: Cotton\nThread Color: Gold\nEmbroidery Position: Middle Chest\nPhoto: https://example.test/ctcx.png",
            'RAW-CTCX-1',
            'CS-QK2571-CX',
            'https://example.test/products/ctcx'
        );
        $this->assertHeatTransferWorkbookIncludesFixedPlacement($result['output_path']);
    }

    private function getDataProcessingMethod($name)
    {
        $reflection = new \ReflectionClass($this->dataProcessingService);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    private function writeSyntheticOrderWorkbook($path)
    {
        $spreadsheet = new \PHPExcel();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Order ID', 'SKU', 'Product Specs', 'Picture', 'Quantity', 'Sales Link'];

        foreach ($headers as $column => $header) {
            $sheet->setCellValueByColumnAndRow($column, 1, $header);
        }

        $rows = [
            [
                'ORDER-1',
                'RAW-CTCX-1',
                "Color: White\nSize: M\nMaterial: Cotton\nThread Color: Gold\nEmbroidery Position: Middle Chest\nPhoto: https://example.test/ctcx.png",
                '',
                1,
                'https://example.test/products/ctcx',
            ],
            [
                'ORDER-2',
                'RAW-PERSON-1',
                "Color: White\nSize: L\nMaterial: Cotton\nPhoto: https://example.test/person.png",
                '',
                2,
                'https://example.test/products/person',
            ],
            [
                'ORDER-3',
                'RAW-BLANKET-1',
                "Color: White\nSize: XL\nMaterial: Fleece",
                '',
                3,
                'https://example.test/products/blanket',
            ],
            [
                'ORDER-4',
                'RAW-HEAT-1',
                "Color: Black\nSize: M\nMaterial: Cotton\nName: Alice\nYear: 1978\nPhoto: https://example.test/design.png",
                '',
                1,
                'https://example.test/products/heat',
            ],
        ];

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValueByColumnAndRow($column, $rowIndex + 2, $value);
            }
        }

        $writer = new \PHPExcel_Writer_Excel2007($spreadsheet);
        $writer->save($path);
    }

    private function assertTemplateWorkbookIncludesReviewColumns($archivePath, $filename, $expectedSpecs, $expectedSku, $expectedCleanedSku, $expectedSalesLink)
    {
        $extractDirectory = $this->makeTempDirectory('template-output-extract-');
        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($archivePath));
        $this->assertTrue($zip->extractTo($extractDirectory, $filename));
        $zip->close();

        $workbookPath = $extractDirectory . DIRECTORY_SEPARATOR . $filename;
        $this->assertFileExists($workbookPath);

        $workbook = \PHPExcel_IOFactory::load($workbookPath);
        $sheet = $workbook->getActiveSheet();
        $lastColumnIndex = \PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn()) - 1;

        $productSpecsColumnIndex = $lastColumnIndex - 3;
        $productSpecsColumnLetter = \PHPExcel_Cell::stringFromColumnIndex($productSpecsColumnIndex);

        $this->assertSame('产品规格', $sheet->getCellByColumnAndRow($productSpecsColumnIndex, 1)->getValue());
        $this->assertSame('sku', $sheet->getCellByColumnAndRow($lastColumnIndex - 2, 1)->getValue());
        $this->assertSame('cleaned_sku', $sheet->getCellByColumnAndRow($lastColumnIndex - 1, 1)->getValue());
        $this->assertSame('产品链接', $sheet->getCellByColumnAndRow($lastColumnIndex, 1)->getValue());
        $this->assertSame($expectedSpecs, $sheet->getCellByColumnAndRow($productSpecsColumnIndex, 2)->getValue());
        $this->assertSame($expectedSku, $sheet->getCellByColumnAndRow($lastColumnIndex - 2, 2)->getValue());
        $this->assertSame($expectedCleanedSku, $sheet->getCellByColumnAndRow($lastColumnIndex - 1, 2)->getValue());
        $this->assertSame($expectedSalesLink, $sheet->getCellByColumnAndRow($lastColumnIndex, 2)->getValue());
        $this->assertTrue($sheet->getStyleByColumnAndRow($productSpecsColumnIndex, 2)->getAlignment()->getWrapText());
        $this->assertSame(45.0, $sheet->getColumnDimension($productSpecsColumnLetter)->getWidth());

        $workbook->disconnectWorksheets();
    }

    private function assertHeatTransferWorkbookIncludesFixedPlacement($archivePath)
    {
        $extractDirectory = $this->makeTempDirectory('heat-transfer-output-extract-');
        $filename = 'order_output_普通烫画衣服0601_e2e.xlsx';
        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($archivePath));
        $this->assertTrue($zip->extractTo($extractDirectory, $filename));
        $zip->close();

        $workbookPath = $extractDirectory . DIRECTORY_SEPARATOR . $filename;
        $this->assertFileExists($workbookPath);

        $workbook = \PHPExcel_IOFactory::load($workbookPath);
        $sheet = $workbook->getActiveSheet();
        $headers = [];
        $lastColumnIndex = \PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn()) - 1;

        for ($column = 0; $column <= $lastColumnIndex; $column++) {
            $headers[$sheet->getCellByColumnAndRow($column, 1)->getValue()] = $column;
        }

        $this->assertSame('左胸口', $sheet->getCellByColumnAndRow($headers['烫画位置'], 2)->getValue());
        $this->assertSame(
            "Name：Alice\nYear：1978\nPhoto：https://example.test/design.png",
            $sheet->getCellByColumnAndRow($headers['胸口信息'], 2)->getValue()
        );

        $workbook->disconnectWorksheets();
    }

    private function makeStaticLookupService()
    {
        return new class extends LookupService {
            public function __construct()
            {
            }

            public function getStyleLookup()
            {
                return [];
            }

            public function getColorLookup()
            {
                return [];
            }

            public function matchStyle($sku, $styleLookup = null)
            {
                return '';
            }

            public function matchColor($specs, $colorLookup = null)
            {
                return '';
            }

            public function extractSize($specs)
            {
                if (preg_match('/^Size:\s*(.+)$/mi', (string) $specs, $matches)) {
                    return trim($matches[1]);
                }

                return '';
            }
        };
    }

    private function makeTempDirectory($prefix)
    {
        $directory = storage_path('app/testing/' . $prefix . uniqid());
        mkdir($directory, 0755, true);
        $this->tempDirectories[] = $directory;

        return $directory;
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
