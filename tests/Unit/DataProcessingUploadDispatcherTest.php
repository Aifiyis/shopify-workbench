<?php

namespace Tests\Unit;

use App\Services\DataProcessingUploadDispatcher;
use Tests\TestCase;

class DataProcessingUploadDispatcherTest extends TestCase
{
    public function test_background_command_runs_artisan_with_higher_memory_limit()
    {
        $dispatcher = new DataProcessingUploadDispatcher();
        $reflection = new \ReflectionClass($dispatcher);
        $method = $reflection->getMethod('backgroundCommand');
        $method->setAccessible(true);

        $command = $method->invoke(
            $dispatcher,
            'C:\\php\\php.exe',
            'D:\\workspace\\shopify-workbench\\artisan',
            123,
            'D:\\workspace\\shopify-workbench\\storage\\logs\\data-processing-upload-123.log'
        );

        $this->assertStringContainsString('-d', $command);
        $this->assertStringContainsString('memory_limit=1024M', $command);
        $this->assertStringContainsString('data-processing:process-upload 123', $command);
    }
}
