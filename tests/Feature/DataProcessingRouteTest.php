<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ProcessedFile;
use App\Services\DataProcessingService;
use App\Services\FileExpirationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataProcessingRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_upload_route_redirects_to_upload_page_instead_of_method_not_allowed()
    {
        $this->withoutMiddleware();

        $response = $this->get('/data-processing/upload');

        $response->assertRedirect(route('data-processing.index'));
        $response->assertSessionHas('error', '请在数据处理页面选择文件，并点击“处理文件”上传。');
    }

    public function test_post_upload_without_file_redirects_to_upload_page_with_clear_error()
    {
        $this->withoutMiddleware();

        $response = $this->post('/data-processing/upload', []);

        $response->assertRedirect(route('data-processing.index'));
        $response->assertSessionHas('error', '未收到上传文件。请重新选择文件后上传；如果问题反复出现，文件可能超过 PHP 上传大小限制。');
    }

    public function test_post_upload_starts_background_processing_instead_of_processing_during_request()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'test-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        $this->mock(DataProcessingService::class, function ($mock) {
            $mock->shouldNotReceive('processOrderFileAll');
        });

        if (class_exists(\App\Services\DataProcessingUploadDispatcher::class)) {
            $this->mock(\App\Services\DataProcessingUploadDispatcher::class, function ($mock) {
                $mock->shouldReceive('dispatch')->once();
            });
        }

        $file = UploadedFile::fake()->create(
            'order_0524 21-0526 09-ln.xlsx',
            128,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->actingAs($admin, 'admin')
            ->post('/data-processing/upload', ['file' => $file]);

        $response->assertRedirect(route('data-processing.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('processed_files', [
            'admin_id' => $admin->id,
            'original_filename' => 'order_0524 21-0526 09-ln.xlsx',
            'status' => 'processing',
        ]);
    }

    public function test_index_handles_missing_expiry_info_without_rendering_error()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'expired-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        ProcessedFile::create([
            'admin_id' => $admin->id,
            'original_filename' => 'existing_order.xlsx',
            'processed_filename' => 'existing_order.zip',
            'file_path' => storage_path('app/public/processed_files/existing_order.zip'),
            'status' => 'completed',
            'uploaded_at' => now(),
            'expires_at' => now()->addHour(),
            'is_downloaded' => false,
        ]);

        $this->mock(FileExpirationService::class, function ($mock) {
            $mock->shouldReceive('cleanExpiredFiles')->once()->andReturn(0);
            $mock->shouldReceive('getExpiryInfo')->once()->andReturn(null);
        });

        $response = $this->actingAs($admin, 'admin')
            ->get('/data-processing');

        $response->assertOk();
        $response->assertSee('existing_order.xlsx');
        $response->assertSee('Expired');
        $response->assertDontSee('Download All');
    }
}
