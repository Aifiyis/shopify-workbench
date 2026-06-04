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
        $this->assertEquals('0601 09-0602 09.xlsx', $result);

        $result = $method->invoke($this->dataProcessingService, 'test_file.csv');
        $this->assertEquals('file.csv', $result);
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
}
