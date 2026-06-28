<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ShopifyStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExistingPagesChineseLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_fully_localized_in_chinese()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('<html lang="zh-CN">', false);
        $response->assertSeeText('千兴工作台');
        $response->assertSeeText('管理员登录');
        $response->assertSeeText('邮箱');
        $response->assertSeeText('密码');
        $response->assertSeeText('记住我');
        $response->assertSeeText('登录');
        $response->assertDontSeeText('Shopify Workbench');
        $response->assertDontSeeText('Admin Portal');
        $response->assertDontSee('linear-gradient', false);
    }

    public function test_dashboard_and_data_processing_pages_use_chinese_operational_copy()
    {
        $admin = $this->createAdmin();
        $store = ShopifyStore::create([
            'shop_name' => '测试店铺',
            'shop_url' => 'test-shop.myshopify.com',
            'access_token' => 'test-token',
            'is_active' => true,
        ]);
        $admin->stores()->attach($store->id, ['access_level' => 'edit']);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('<title>工作台 - 千兴工作台</title>', false)
            ->assertSeeText('选择店铺')
            ->assertSeeText('选择要处理数据的店铺')
            ->assertSeeText('启用')
            ->assertDontSeeText('Select a Store')
            ->assertDontSeeText('Active');

        $this->actingAs($admin, 'admin')
            ->get(route('data-processing.index'))
            ->assertOk()
            ->assertSee('<title>数据处理 - 千兴工作台</title>', false)
            ->assertSeeText('上传订单文件')
            ->assertSeeText('处理文件')
            ->assertSeeText('暂无处理文件')
            ->assertSeeText('确认删除')
            ->assertDontSeeText('Data Processing')
            ->assertDontSeeText('Process File')
            ->assertDontSee("onsubmit=\"return confirm", false);
    }

    public function test_unrouted_order_and_report_sources_are_localized_without_changing_field_keys()
    {
        $sources = [
            resource_path('views/orders/index.blade.php'),
            resource_path('views/reports/index.blade.php'),
            resource_path('views/reports/form.blade.php'),
            resource_path('views/reports/schedule.blade.php'),
        ];
        $contents = implode("\n", array_map('file_get_contents', $sources));

        foreach (['订单', '返回工作台', '报表', '新增报表', '编辑报表', '保存', '取消', '下载'] as $text) {
            $this->assertStringContainsString($text, $contents);
        }

        foreach (['Orders - Shopify Workbench', 'Create Report', 'Edit Report', 'Back to reports', 'Save report', '>Cancel<'] as $text) {
            $this->assertStringNotContainsString($text, $contents);
        }

        $this->assertStringContainsString("input.name = 'selected_fields[]';", $contents);
        $this->assertStringContainsString("route('reports.schedule.save'", $contents);
        $this->assertStringContainsString("route(\"orders.refresh\")", $contents);
    }

    public function test_user_visible_controller_and_upload_redirect_messages_are_chinese()
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('data-processing.upload.redirect'))
            ->assertRedirect(route('data-processing.index'))
            ->assertSessionHas('error', '请在数据处理页面选择文件，并点击“处理文件”上传。');

        $this->actingAs($admin, 'admin')
            ->post(route('data-processing.upload'), [])
            ->assertRedirect(route('data-processing.index'))
            ->assertSessionHas('error', '未收到上传文件。请重新选择文件后上传；如果问题反复出现，文件可能超过 PHP 上传大小限制。');

        $controllerSources = implode("\n", [
            file_get_contents(app_path('Http/Controllers/DataProcessingController.php')),
            file_get_contents(app_path('Http/Controllers/OrderController.php')),
            file_get_contents(app_path('Http/Controllers/ReportController.php')),
        ]);

        foreach (['Unauthorized access to this store', 'Orders refreshed successfully', 'Report saved.', 'Schedule saved.'] as $text) {
            $this->assertStringNotContainsString($text, $controllerSources);
        }
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'name' => '本地化管理员',
            'email' => 'localization@example.test',
            'password' => Hash::make('password123'),
            'role' => 'super',
            'is_active' => true,
        ]);
    }
}
