<?php

namespace Tests\Unit;

use App\Services\DataProcessingService;
use App\Services\LookupService;
use Tests\TestCase;

class DataProcessingServiceTest extends TestCase
{
    private $dataProcessingService;
    private $lookupService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lookupService = new LookupService();
        $this->dataProcessingService = new DataProcessingService($this->lookupService);
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

    public function test_apply_ctcx_qk0743_rules()
    {
        $method = $this->getDataProcessingMethod('applyCtcxSkuRules');
        $values = ['date', 'order', '', '', '', '', '', 1];
        $specs = implode("\n", [
            'Color: White',
            'Size: M',
            'Material: Cotton',
            'State Options: California',
            'Year: 2026',
            'Text Thread Color: Red',
        ]);

        $result = $method->invoke($this->dataProcessingService, $values, 'ABC-CS-QK0743-CX-001', $specs, [
            'Red' => '红色',
        ]);

        $this->assertEquals("第一行：California\n第二行：EST. 2026", $result[19]);
        $this->assertEquals('红色', $result[21]);
        $this->assertEquals('胸部中央', $result[26]);
    }

    public function test_apply_ctcx_qk2571_rules()
    {
        $method = $this->getDataProcessingMethod('applyCtcxSkuRules');
        $values = ['date', 'order', '', '', '', '', '', 1];
        $specs = implode("\n", [
            'Color: White',
            'Size: M',
            'Material: Cotton',
            'Thread Color: Gold',
            'Embroidery Position: Middle Chest',
            'Photo: https://example.test/photo.png',
        ]);

        $result = $method->invoke($this->dataProcessingService, $values, 'ABC-CS-QK2571-CX-001', $specs, []);

        $this->assertEquals('Gold', $result[17]);
        $this->assertEquals('全彩', $result[20]);
        $this->assertEquals('https://example.test/photo.png', $result[22]);
        $this->assertEquals('胸部中央', $result[26]);
    }

    public function test_format_ctcx_est_year_line()
    {
        $method = $this->getDataProcessingMethod('formatCtcxEstYearLine');

        $this->assertEquals('第二行：EST. 1990', $method->invoke($this->dataProcessingService, '1990'));
        $this->assertEquals('第二行：EST. 1990', $method->invoke($this->dataProcessingService, 'EST.1990'));
        $this->assertEquals('第二行：EST. 1990', $method->invoke($this->dataProcessingService, 'Est 1990'));
        $this->assertEquals('第二行：EST. 1990-1992', $method->invoke($this->dataProcessingService, 'Est 1990-1992'));
    }

    public function test_map_embroidery_position()
    {
        $method = $this->getDataProcessingMethod('mapEmbroideryPosition');

        $this->assertEquals('胸部中央', $method->invoke($this->dataProcessingService, 'Middle Chest'));
        $this->assertEquals('胸部中央', $method->invoke($this->dataProcessingService, 'middle chest'));
        $this->assertEquals('胸部中央', $method->invoke($this->dataProcessingService, 'Center Chest'));
        $this->assertEquals('左胸口', $method->invoke($this->dataProcessingService, 'left chest'));
        $this->assertEquals('右胸口', $method->invoke($this->dataProcessingService, 'Right Chest'));
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

    private function getDataProcessingMethod($name)
    {
        $reflection = new \ReflectionClass($this->dataProcessingService);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
