<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ProcessedFile;
use App\Services\DataProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataProcessingProcessUploadCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_upload_command_completes_a_processing_file()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'command-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        $tempPath = storage_path('app/temp/test-command-upload.xlsx');
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0777, true);
        }
        file_put_contents($tempPath, 'test upload');

        $processedFile = ProcessedFile::create([
            'admin_id' => $admin->id,
            'original_filename' => 'order_test.xlsx',
            'processed_filename' => 'processing_test.xlsx',
            'file_path' => $tempPath,
            'status' => 'processing',
            'uploaded_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->mock(DataProcessingService::class, function ($mock) use ($tempPath) {
            $mock->shouldReceive('processOrderFileAll')
                ->once()
                ->with($tempPath, 'order_test.xlsx')
                ->andReturn([
                    'success' => true,
                    'output_filename' => 'order_outputs_test.zip',
                    'output_path' => storage_path('app/public/processed_files/order_outputs_test.zip'),
                    'rows_processed' => 351,
                    'template_rows_processed' => 74,
                ]);
        });

        $this->artisan('data-processing:process-upload', [
            'processedFileId' => $processedFile->id,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('processed_files', [
            'id' => $processedFile->id,
            'processed_filename' => 'order_outputs_test.zip',
            'file_path' => storage_path('app/public/processed_files/order_outputs_test.zip'),
            'status' => 'completed',
            'error_message' => null,
        ]);
        $this->assertFileDoesNotExist($tempPath);
    }

    public function test_process_upload_command_fatal_shutdown_marks_processing_file_failed()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'fatal-command-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        $processedFile = ProcessedFile::create([
            'admin_id' => $admin->id,
            'original_filename' => 'order_test.xlsx',
            'processed_filename' => 'processing_test.xlsx',
            'file_path' => storage_path('app/temp/test-command-upload.xlsx'),
            'status' => 'processing',
            'uploaded_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $command = app(\App\Console\Commands\ProcessDataProcessingUpload::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('markProcessingFileFailedFromFatal');
        $method->setAccessible(true);

        $method->invoke($command, $processedFile->id, [
            'type' => E_ERROR,
            'message' => 'Allowed memory size of 268435456 bytes exhausted',
            'file' => 'D:\\workspace\\shopify-workbench\\app\\Services\\DataProcessingService.php',
            'line' => 1373,
        ]);

        $this->assertDatabaseHas('processed_files', [
            'id' => $processedFile->id,
            'status' => 'failed',
            'error_message' => 'Fatal error: Allowed memory size of 268435456 bytes exhausted at D:\\workspace\\shopify-workbench\\app\\Services\\DataProcessingService.php:1373',
        ]);
    }
}
