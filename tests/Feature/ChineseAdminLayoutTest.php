<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChineseAdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_the_shared_chinese_admin_layout()
    {
        $admin = Admin::create([
            'name' => '超级管理员',
            'email' => 'layout@example.test',
            'password' => 'test-password',
            'role' => 'super',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('<html lang="zh-CN">', false);
        $response->assertSeeText('千兴工作台');
        $response->assertSeeText('工作台');
        $response->assertSeeText('数据处理');
        $response->assertSeeText('SKU 产品类型');
        $response->assertSeeText('订单处理配置');
        $response->assertSeeText('工艺层级管理');
        $response->assertSeeText('退出登录');
        $response->assertDontSeeText('Shopify Workbench');
        $response->assertDontSeeText('Dashboard');
        $response->assertDontSeeText('Logout');
    }
}
