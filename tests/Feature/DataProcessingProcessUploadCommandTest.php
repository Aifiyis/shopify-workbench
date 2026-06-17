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
}
