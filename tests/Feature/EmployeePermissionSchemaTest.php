<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProductProcessingCraft;
use App\Models\SkuMatchProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeePermissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_permission_and_business_staff_schema_is_wired()
    {
        $admin = Admin::create([
            'name' => 'Test Employee',
            'email' => 'employee@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'name' => '李鑫',
            'company_name' => '千兴科技',
            'admin_id' => $admin->id,
            'is_active' => true,
        ]);

        $advertising = Position::create([
            'name' => '广告',
            'code' => 'advertising',
        ]);
        $artworkProcessing = Position::create([
            'name' => '图画处理',
            'code' => 'artwork_processing',
        ]);
        $employee->positions()->attach([
            $advertising->id,
            $artworkProcessing->id,
        ]);

        $permission = Permission::create([
            'name' => '编辑产品类型',
            'code' => 'product_type.update',
            'is_delegable' => true,
        ]);
        $advertising->permissions()->attach($permission);
        DB::table('role_permission')->insert([
            'role' => 'manager',
            'permission_id' => $permission->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processingCraft = ProductProcessingCraft::create([
            'chinese_name' => '彩图刺绣',
            'order_processor_employee_id' => $employee->id,
            'artwork_processor_employee_id' => $employee->id,
            'procurement_processor_employee_id' => $employee->id,
            'settlement_method' => '月结',
        ]);
        $skuMatch = SkuMatchProductType::create([
            'original_sku' => 'RAW-EMPLOYEE-1',
            'cleaned_sku' => 'CLEAN-EMPLOYEE-1',
            'chinese_name' => $processingCraft->chinese_name,
            'product_lister_employee_id' => $employee->id,
        ]);

        $this->assertCount(2, $employee->positions);
        $this->assertTrue($employee->is($admin->employee));
        $this->assertTrue($permission->is($advertising->permissions->first()));
        $this->assertDatabaseHas('role_permission', [
            'role' => 'manager',
            'permission_id' => $permission->id,
        ]);

        foreach ([
            'employees',
            'positions',
            'permissions',
            'employee_position',
            'position_permission',
            'role_permission',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumn('admins', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('sku_match_product_type', 'product_lister_employee_id'));
        $this->assertTrue(Schema::hasColumns('product_processing_craft', [
            'order_processor_employee_id',
            'artwork_processor_employee_id',
            'procurement_processor_employee_id',
            'settlement_method',
        ]));

        $this->assertTrue($employee->is($skuMatch->productListerEmployee));
        $this->assertTrue($employee->is($processingCraft->orderProcessorEmployee));
        $this->assertTrue($employee->is($processingCraft->artworkProcessorEmployee));
        $this->assertTrue($employee->is($processingCraft->procurementProcessorEmployee));
    }
}
