<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ProcessedFile;
use App\Services\FileExpirationService;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class DataProcessingTest extends TestCase
{
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');

        // Create a test admin
        $this->admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'test@test.com',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);
    }

    public function test_processed_file_can_be_created()
    {
        $file = ProcessedFile::create([
            'admin_id' => $this->admin->id,
            'original_filename' => 'test_order.xlsx',
            'processed_filename' => 'order_output_test_order.xlsx',
            'file_path' => storage_path('app/public/processed_files/order_output_test_order.xlsx'),
            'status' => 'completed',
            'uploaded_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertNotNull($file->id);
        $this->assertEquals('test_order.xlsx', $file->original_filename);
        $this->assertFalse($file->isExpired());
    }

    public function test_file_expiration_service()
    {
        $service = new FileExpirationService(1); // 1 hour TTL

        $file = ProcessedFile::create([
            'admin_id' => $this->admin->id,
            'original_filename' => 'test_order.xlsx',
            'processed_filename' => 'order_output_test_order.xlsx',
            'file_path' => storage_path('app/public/processed_files/order_output_test_order.xlsx'),
            'status' => 'completed',
            'uploaded_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertFalse($service->isFileExpired($file->id));

        // Check expiry info
        $info = $service->getExpiryInfo($file->id);
        $this->assertNotNull($info);
        $this->assertFalse($info['is_expired']);
        $this->assertGreaterThan(0, $info['expires_in_minutes']);
    }

    public function test_mark_file_as_downloaded()
    {
        $service = new FileExpirationService(1);

        $file = ProcessedFile::create([
            'admin_id' => $this->admin->id,
            'original_filename' => 'test_order.xlsx',
            'processed_filename' => 'order_output_test_order.xlsx',
            'file_path' => storage_path('app/public/processed_files/order_output_test_order.xlsx'),
            'status' => 'completed',
            'uploaded_at' => now(),
            'expires_at' => now()->addHour(),
            'is_downloaded' => false,
        ]);

        $this->assertFalse($file->is_downloaded);

        $service->markAsDownloaded($file->id);

        $updatedFile = ProcessedFile::find($file->id);
        $this->assertTrue($updatedFile->is_downloaded);
        $this->assertNotNull($updatedFile->downloaded_at);
    }

    public function test_file_expires_after_one_hour()
    {
        $file = ProcessedFile::create([
            'admin_id' => $this->admin->id,
            'original_filename' => 'test_order.xlsx',
            'processed_filename' => 'order_output_test_order.xlsx',
            'file_path' => storage_path('app/public/processed_files/order_output_test_order.xlsx'),
            'status' => 'completed',
            'uploaded_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($file->isExpired());
    }
}
