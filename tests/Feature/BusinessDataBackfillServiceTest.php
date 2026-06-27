<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use App\Services\BusinessDataBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessDataBackfillServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_product_type_union_without_creating_fake_skus()
    {
        $processingWithSku = ProductProcessingCraft::create([
            'chinese_name' => '刺绣',
        ]);
        $processingOnly = ProductProcessingCraft::create([
            'chinese_name' => '热转印',
        ]);

        $sku = SkuMatchProductType::create([
            'original_sku' => 'LEGACY-SKU-1',
            'cleaned_sku' => 'LEGACY-SKU-1',
            'chinese_name' => $processingWithSku->chinese_name,
        ]);

        $result = app(BusinessDataBackfillService::class)->run();

        $this->assertSame(2, $result['product_types']);
        $this->assertSame(2, ProductType::count());
        $this->assertSame(1, SkuMatchProductType::count());
        $this->assertNotNull($sku->fresh()->product_type_id);
        $this->assertNotNull($processingWithSku->fresh()->product_type_id);
        $this->assertNotNull($processingOnly->fresh()->product_type_id);
        $this->assertDatabaseMissing('sku_match_product_type', [
            'chinese_name' => '热转印',
        ]);
    }

    public function test_normalizes_staff_assigns_positions_and_tracks_unresolved_values()
    {
        $processing = ProductProcessingCraft::create([
            'chinese_name' => '刺绣',
            'order_processor' => ' 张三 ',
            'artwork_processor' => '王五',
            'procurement_processor' => '李梦瑶（月结）',
        ]);
        $unresolved = ProductProcessingCraft::create([
            'chinese_name' => '热转印',
            'order_processor' => '李鑫/万芸君',
            'artwork_processor' => '预览图',
            'procurement_processor' => '   ',
        ]);
        $sku = SkuMatchProductType::create([
            'original_sku' => 'LEGACY-SKU-1',
            'cleaned_sku' => 'LEGACY-SKU-1',
            'chinese_name' => $processing->chinese_name,
            'product_lister' => '张三',
        ]);

        $result = app(BusinessDataBackfillService::class)->run();

        $processing->refresh();
        $unresolved->refresh();
        $sku->refresh();

        $this->assertSame('李梦瑶（月结）', $processing->procurement_processor);
        $this->assertSame('月结', $processing->settlement_method);
        $this->assertSame('李梦瑶', $processing->procurementProcessorEmployee->name);
        $this->assertTrue(
            $processing->procurementProcessorEmployee->positions->contains('code', 'procurement')
        );

        $zhangSan = Employee::where('name', '张三')->firstOrFail();
        $this->assertSame(
            ['advertising', 'order_processing'],
            $zhangSan->positions()->orderBy('code')->pluck('code')->all()
        );
        $this->assertEquals($zhangSan->id, $sku->product_lister_employee_id);
        $this->assertEquals($zhangSan->id, $processing->order_processor_employee_id);
        $this->assertTrue(
            $processing->artworkProcessorEmployee->positions->contains('code', 'artwork_processing')
        );
        $this->assertTrue(Position::where('code', 'operations')->exists());

        $this->assertNull($unresolved->order_processor_employee_id);
        $this->assertNull($unresolved->artwork_processor_employee_id);
        $this->assertNull($unresolved->procurement_processor_employee_id);
        $this->assertSame('李鑫/万芸君', $unresolved->order_processor);
        $this->assertSame('预览图', $unresolved->artwork_processor);
        $this->assertSame('   ', $unresolved->procurement_processor);

        $this->assertSame(3, $result['employees']);
        $this->assertSame(4, $result['links']);
        $this->assertSame(3, $result['unresolved_values']);
    }

    public function test_restores_soft_deleted_records_seeds_permissions_and_is_idempotent()
    {
        $productType = ProductType::create(['chinese_name' => '刺绣']);
        $productType->delete();

        $advertising = Position::create([
            'name' => '旧广告',
            'code' => 'advertising',
            'is_active' => false,
        ]);
        $advertising->delete();

        $employee = Employee::create([
            'name' => '张三',
            'is_active' => false,
        ]);
        $employee->delete();

        $processing = ProductProcessingCraft::create([
            'chinese_name' => '刺绣',
            'order_processor' => '张三',
        ]);
        SkuMatchProductType::create([
            'original_sku' => 'LEGACY-SKU-1',
            'cleaned_sku' => 'LEGACY-SKU-1',
            'chinese_name' => $processing->chinese_name,
            'product_lister' => '张三',
        ]);

        $service = app(BusinessDataBackfillService::class);
        $firstResult = $service->run();

        $this->assertFalse($productType->fresh()->trashed());
        $this->assertFalse($advertising->fresh()->trashed());
        $this->assertTrue($advertising->fresh()->is_active);
        $this->assertSame('广告', $advertising->fresh()->name);
        $this->assertFalse($employee->fresh()->trashed());
        $this->assertTrue($employee->fresh()->is_active);

        $this->assertSame(5, Position::count());
        $this->assertSame(7, Permission::count());
        $this->assertSame(7, DB::table('role_permission')->where('role', 'manager')->count());
        $this->assertSame(8, DB::table('position_permission')->count());
        $this->assertSame(6, Permission::where('is_delegable', true)->count());
        $this->assertFalse(
            Permission::where('code', 'permissions.assign')->firstOrFail()->is_delegable
        );
        $this->assertPositionPermissions('advertising', ['sku_product_types.manage']);
        $this->assertPositionPermissions('operations', ['sku_product_types.manage']);
        $this->assertPositionPermissions('procurement', [
            'order_processing.manage',
            'processing_crafts.manage',
        ]);
        $this->assertPositionPermissions('order_processing', [
            'order_processing.manage',
            'processing_crafts.manage',
        ]);
        $this->assertPositionPermissions('artwork_processing', [
            'order_processing.manage',
            'processing_crafts.manage',
        ]);

        $countsAfterFirstRun = $this->businessCounts();
        $secondResult = $service->run();

        $this->assertSame($firstResult, $secondResult);
        $this->assertSame($countsAfterFirstRun, $this->businessCounts());
    }

    public function test_rerun_preserves_existing_manager_permission_timestamps()
    {
        $service = app(BusinessDataBackfillService::class);
        $service->run();

        $permissionId = Permission::where('code', 'sku_product_types.manage')->value('id');
        DB::table('role_permission')
            ->where('role', 'manager')
            ->where('permission_id', $permissionId)
            ->update([
                'created_at' => '2020-01-02 03:04:05',
                'updated_at' => '2020-01-02 03:04:05',
            ]);

        $before = DB::table('role_permission')
            ->where('role', 'manager')
            ->where('permission_id', $permissionId)
            ->first();

        $service->run();

        $after = DB::table('role_permission')
            ->where('role', 'manager')
            ->where('permission_id', $permissionId)
            ->first();

        $this->assertSame($before->created_at, $after->created_at);
        $this->assertSame($before->updated_at, $after->updated_at);
    }

    private function assertPositionPermissions($positionCode, array $permissionCodes)
    {
        $actual = Position::where('code', $positionCode)
            ->firstOrFail()
            ->permissions()
            ->orderBy('code')
            ->pluck('code')
            ->all();

        sort($permissionCodes);

        $this->assertSame($permissionCodes, $actual);
    }

    private function businessCounts()
    {
        return [
            'product_types' => ProductType::count(),
            'employees' => Employee::count(),
            'positions' => Position::count(),
            'permissions' => Permission::count(),
            'employee_position' => DB::table('employee_position')->count(),
            'position_permission' => DB::table('position_permission')->count(),
            'role_permission' => DB::table('role_permission')->count(),
            'sku_matches' => SkuMatchProductType::count(),
            'processing_crafts' => ProductProcessingCraft::count(),
        ];
    }
}
