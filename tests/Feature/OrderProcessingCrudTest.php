<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderProcessingCrudTest extends TestCase
{
    use RefreshDatabase;

    private $positions = [];
    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::create([
            'name' => '订单处理配置管理',
            'code' => 'order_processing.manage',
            'is_delegable' => true,
        ]);

        foreach ([
            'order_processing' => '订单处理',
            'artwork_processing' => '图画处理',
            'procurement' => '采购',
            'finance' => '财务',
        ] as $code => $name) {
            $position = Position::create([
                'name' => $name,
                'code' => $code,
                'is_active' => true,
            ]);
            if ($code !== 'finance') {
                $position->permissions()->attach($permission->id);
            }
            $this->positions[$code] = $position;
        }
    }

    public function test_active_user_searches_all_fields_filters_and_paginates_by_fifty()
    {
        $viewer = $this->createActor('finance');
        $targetType = ProductType::create(['chinese_name' => '搜索产品类型']);
        $targetCraft = ProcessingCraftNode::create([
            'name' => '搜索工艺',
            'path' => '根节点-搜索工艺',
        ]);
        $orderEmployee = $this->createEmployee('搜索订单员工', 'order_processing');
        $artworkEmployee = $this->createEmployee('搜索图画员工', 'artwork_processing');
        $procurementEmployee = $this->createEmployee('搜索采购员工', 'procurement');
        $target = ProductProcessingCraft::create([
            'product_type_id' => $targetType->id,
            'chinese_name' => '旧产品快照',
            'craft_id' => $targetCraft->id,
            'settlement_method' => '搜索结算',
            'spreadsheet_template' => '搜索模板',
        ]);
        $this->sync($target, 'orderProcessorEmployees', 'order_processing', [$orderEmployee]);
        $this->sync($target, 'artworkProcessorEmployees', 'artwork_processing', [$artworkEmployee]);
        $this->sync($target, 'procurementProcessorEmployees', 'procurement', [$procurementEmployee]);

        for ($index = 1; $index <= 50; $index++) {
            $type = ProductType::create(['chinese_name' => '分页类型 '.$index]);
            ProductProcessingCraft::create([
                'product_type_id' => $type->id,
                'chinese_name' => $type->chinese_name,
            ]);
        }

        $this->actingAs($viewer, 'admin')
            ->get(route('order-processing.index'))
            ->assertOk()
            ->assertViewHas('configurations', function ($paginator) {
                return $paginator->perPage() === 50
                    && $paginator->count() === 50
                    && $paginator->lastPage() === 2;
            });

        foreach ([
            '搜索产品类型',
            '搜索工艺',
            '根节点-搜索工艺',
            '搜索结算',
            '搜索模板',
            '搜索订单员工',
            '搜索图画员工',
            '搜索采购员工',
        ] as $search) {
            $this->actingAs($viewer, 'admin')
                ->get(route('order-processing.index', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('configurations', function ($paginator) use ($target) {
                    return $paginator->total() === 1 && $paginator->first()->is($target);
                });
        }

        $this->actingAs($viewer, 'admin')
            ->get(route('order-processing.index', [
                'product_type_id' => $targetType->id,
                'craft_id' => $targetCraft->id,
            ]))
            ->assertOk()
            ->assertViewHas('configurations', function ($paginator) use ($target) {
                return $paginator->total() === 1 && $paginator->first()->is($target);
            });
    }

    public function test_processing_positions_are_authorized_to_create_update_and_delete()
    {
        foreach (['procurement', 'order_processing', 'artwork_processing'] as $code) {
            $actor = $this->createActor($code);
            $type = ProductType::create(['chinese_name' => $code.' 权限类型']);

            $this->actingAs($actor, 'admin')
                ->post(route('order-processing.store'), [
                    'product_type_id' => $type->id,
                    'settlement_method' => '月结',
                ])
                ->assertRedirect(route('order-processing.index'));

            $configuration = ProductProcessingCraft::where('product_type_id', $type->id)
                ->firstOrFail();

            $this->actingAs($actor, 'admin')
                ->put(route('order-processing.update', $configuration), [
                    'product_type_id' => $type->id,
                    'settlement_method' => '周结',
                ])
                ->assertRedirect(route('order-processing.index'));
            $this->assertSame('周结', $configuration->fresh()->settlement_method);

            $this->actingAs($actor, 'admin')
                ->delete(route('order-processing.destroy', $configuration))
                ->assertRedirect(route('order-processing.index'));
            $this->assertSoftDeleted('product_processing_craft', ['id' => $configuration->id]);
        }
    }

    public function test_two_or_more_employees_per_group_are_persisted_and_listed()
    {
        $actor = $this->createActor('order_processing');
        $type = ProductType::create(['chinese_name' => '多人配置类型']);
        $groups = [
            'order_processor_employee_ids' => [
                $this->createEmployee('订单甲', 'order_processing'),
                $this->createEmployee('订单乙', 'order_processing'),
            ],
            'artwork_processor_employee_ids' => [
                $this->createEmployee('图画甲', 'artwork_processing'),
                $this->createEmployee('图画乙', 'artwork_processing'),
            ],
            'procurement_processor_employee_ids' => [
                $this->createEmployee('采购甲', 'procurement'),
                $this->createEmployee('采购乙', 'procurement'),
            ],
        ];

        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), [
                'product_type_id' => $type->id,
                'order_processor_employee_ids' => collect($groups['order_processor_employee_ids'])->pluck('id')->all(),
                'artwork_processor_employee_ids' => collect($groups['artwork_processor_employee_ids'])->pluck('id')->all(),
                'procurement_processor_employee_ids' => collect($groups['procurement_processor_employee_ids'])->pluck('id')->all(),
            ])
            ->assertRedirect(route('order-processing.index'));

        $configuration = ProductProcessingCraft::where('product_type_id', $type->id)
            ->firstOrFail();
        $this->assertCount(2, $configuration->orderProcessorEmployees);
        $this->assertCount(2, $configuration->artworkProcessorEmployees);
        $this->assertCount(2, $configuration->procurementProcessorEmployees);
        $this->assertSame(6, DB::table('product_processing_craft_employee_assignment')->count());

        $this->actingAs($actor, 'admin')
            ->get(route('order-processing.index'))
            ->assertOk()
            ->assertSee('订单甲、订单乙')
            ->assertSee('图画甲、图画乙')
            ->assertSee('采购甲、采购乙');
    }

    public function test_group_updates_are_independent_same_employee_can_hold_multiple_types_and_legacy_fields_do_not_change()
    {
        $actor = $this->createActor('artwork_processing');
        $initialType = ProductType::create(['chinese_name' => '快照初始类型']);
        $updatedType = ProductType::create(['chinese_name' => '快照更新类型']);
        $legacyOrder = $this->createEmployee('旧单人订单', 'order_processing');
        $legacyArtwork = $this->createEmployee('旧单人图画', 'artwork_processing');
        $legacyProcurement = $this->createEmployee('旧单人采购', 'procurement');
        $shared = $this->createEmployee('跨组员工', ['order_processing', 'artwork_processing']);
        $replacement = $this->createEmployee('订单替换员工', 'order_processing');
        $configuration = ProductProcessingCraft::create([
            'product_type_id' => $initialType->id,
            'chinese_name' => $initialType->chinese_name,
            'order_processor' => "旧订单\0文本",
            'artwork_processor' => '旧图画文本',
            'procurement_processor' => '旧采购文本',
            'order_processor_employee_id' => $legacyOrder->id,
            'artwork_processor_employee_id' => $legacyArtwork->id,
            'procurement_processor_employee_id' => $legacyProcurement->id,
        ]);
        $this->sync($configuration, 'orderProcessorEmployees', 'order_processing', [$shared]);
        $this->sync($configuration, 'artworkProcessorEmployees', 'artwork_processing', [$shared]);

        $legacyBefore = DB::table('product_processing_craft')
            ->where('id', $configuration->id)
            ->first([
                'order_processor',
                'artwork_processor',
                'procurement_processor',
                'order_processor_employee_id',
                'artwork_processor_employee_id',
                'procurement_processor_employee_id',
            ]);

        $this->actingAs($actor, 'admin')
            ->put(route('order-processing.update', $configuration), [
                'product_type_id' => $updatedType->id,
                'order_processor_employee_ids' => [$replacement->id],
                'artwork_processor_employee_ids' => [$shared->id],
                'procurement_processor_employee_ids' => [],
            ])
            ->assertRedirect(route('order-processing.index'));

        $configuration->refresh();
        $this->assertEquals($updatedType->id, $configuration->product_type_id);
        $this->assertSame($updatedType->chinese_name, $configuration->chinese_name);
        $this->assertSame([$replacement->id], $configuration->orderProcessorEmployees->pluck('id')->all());
        $this->assertSame([$shared->id], $configuration->artworkProcessorEmployees->pluck('id')->all());
        $this->assertCount(0, $configuration->procurementProcessorEmployees);
        $this->assertDatabaseHas('product_processing_craft_employee_assignment', [
            'product_processing_craft_id' => $configuration->id,
            'employee_id' => $shared->id,
            'assignment_type' => 'artwork_processing',
        ]);
        $this->assertDatabaseMissing('product_processing_craft_employee_assignment', [
            'product_processing_craft_id' => $configuration->id,
            'employee_id' => $shared->id,
            'assignment_type' => 'order_processing',
        ]);

        $legacyAfter = DB::table('product_processing_craft')
            ->where('id', $configuration->id)
            ->first([
                'order_processor',
                'artwork_processor',
                'procurement_processor',
                'order_processor_employee_id',
                'artwork_processor_employee_id',
                'procurement_processor_employee_id',
            ]);
        $this->assertEquals($legacyBefore, $legacyAfter);
    }

    public function test_actor_without_management_permission_gets_403_for_mutations()
    {
        $actor = $this->createActor('finance');
        $type = ProductType::create(['chinese_name' => '禁止操作类型']);
        $configuration = ProductProcessingCraft::create([
            'product_type_id' => $type->id,
            'chinese_name' => $type->chinese_name,
        ]);

        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), ['product_type_id' => $type->id])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->put(route('order-processing.update', $configuration), [
                'product_type_id' => $type->id,
            ])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->delete(route('order-processing.destroy', $configuration))
            ->assertForbidden();
    }

    public function test_invalid_group_employees_are_rejected()
    {
        $actor = $this->createActor('procurement');
        $type = ProductType::create(['chinese_name' => '员工校验类型']);
        $inactive = $this->createEmployee('停用订单员工', 'order_processing', false);
        $deleted = $this->createEmployee('删除图画员工', 'artwork_processing');
        $deleted->delete();
        $wrongPosition = $this->createEmployee('错误职位员工', 'finance');

        foreach ([
            'order_processor_employee_ids' => $inactive->id,
            'artwork_processor_employee_ids' => $deleted->id,
            'procurement_processor_employee_ids' => $wrongPosition->id,
        ] as $field => $employeeId) {
            $this->actingAs($actor, 'admin')
                ->post(route('order-processing.store'), [
                    'product_type_id' => $type->id,
                    $field => [$employeeId],
                ])
                ->assertSessionHasErrors($field.'.0');
        }

        $duplicate = $this->createEmployee('重复订单员工', 'order_processing');
        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), [
                'product_type_id' => $type->id,
                'order_processor_employee_ids' => [$duplicate->id, $duplicate->id],
            ])
            ->assertSessionHasErrors('order_processor_employee_ids.0');
    }

    public function test_historical_assignments_can_remain_and_show_only_in_their_current_group()
    {
        $actor = $this->createActor('order_processing');
        $typeA = ProductType::create(['chinese_name' => '历史配置甲']);
        $typeB = ProductType::create(['chinese_name' => '历史配置乙']);
        $inactive = $this->createEmployee('历史停用员工', 'order_processing', false);
        $deleted = $this->createEmployee('历史删除员工', 'artwork_processing');
        $positionChanged = $this->createEmployee('历史职位变更员工', 'order_processing');
        $configA = ProductProcessingCraft::create([
            'product_type_id' => $typeA->id,
            'chinese_name' => $typeA->chinese_name,
        ]);
        $configB = ProductProcessingCraft::create([
            'product_type_id' => $typeB->id,
            'chinese_name' => $typeB->chinese_name,
        ]);
        $this->sync($configA, 'orderProcessorEmployees', 'order_processing', [
            $inactive,
            $positionChanged,
        ]);
        $this->sync($configA, 'artworkProcessorEmployees', 'artwork_processing', [$deleted]);
        $deleted->delete();
        $positionChanged->positions()->detach();

        $this->actingAs($actor, 'admin')
            ->get(route('order-processing.edit', $configA))
            ->assertOk()
            ->assertSee('历史停用员工（已停用）')
            ->assertSee('历史删除员工（已删除）')
            ->assertSee('历史职位变更员工')
            ->assertViewHas('orderEmployees', function ($employees) use ($inactive, $positionChanged) {
                return $employees->contains('id', $inactive->id)
                    && $employees->contains('id', $positionChanged->id);
            })
            ->assertViewHas('artworkEmployees', function ($employees) use ($inactive, $deleted) {
                return !$employees->contains('id', $inactive->id)
                    && $employees->contains('id', $deleted->id);
            });

        $this->actingAs($actor, 'admin')
            ->put(route('order-processing.update', $configA), [
                'product_type_id' => $typeA->id,
                'order_processor_employee_ids' => [$inactive->id, $positionChanged->id],
                'artwork_processor_employee_ids' => [$deleted->id],
            ])
            ->assertRedirect(route('order-processing.index'));

        $this->actingAs($actor, 'admin')
            ->get(route('order-processing.index'))
            ->assertOk()
            ->assertSee('历史停用员工（已停用）')
            ->assertSee('历史删除员工（已删除）');

        $this->actingAs($actor, 'admin')
            ->put(route('order-processing.update', $configB), [
                'product_type_id' => $typeB->id,
                'order_processor_employee_ids' => [$inactive->id],
            ])
            ->assertSessionHasErrors('order_processor_employee_ids.0');
        $this->actingAs($actor, 'admin')
            ->put(route('order-processing.update', $configB), [
                'product_type_id' => $typeB->id,
                'order_processor_employee_ids' => [$positionChanged->id],
            ])
            ->assertSessionHasErrors('order_processor_employee_ids.0');
        $this->actingAs($actor, 'admin')
            ->put(route('order-processing.update', $configA), [
                'product_type_id' => $typeA->id,
                'artwork_processor_employee_ids' => [$inactive->id],
            ])
            ->assertSessionHasErrors('artwork_processor_employee_ids.0');
    }

    public function test_product_type_and_craft_must_be_active_and_product_type_is_unique_across_history()
    {
        $actor = $this->createActor('procurement');
        $activeType = ProductType::create(['chinese_name' => '有效产品类型']);
        $deletedType = ProductType::create(['chinese_name' => '删除产品类型']);
        $deletedType->delete();
        $activeCraft = ProcessingCraftNode::create([
            'name' => '有效工艺',
            'path' => '有效工艺',
        ]);
        $deletedCraft = ProcessingCraftNode::create([
            'name' => '删除工艺',
            'path' => '删除工艺',
        ]);
        $deletedCraft->delete();

        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), [
                'product_type_id' => $deletedType->id,
                'craft_id' => $activeCraft->id,
            ])
            ->assertSessionHasErrors('product_type_id');
        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), [
                'product_type_id' => $activeType->id,
                'craft_id' => $deletedCraft->id,
            ])
            ->assertSessionHasErrors('craft_id');

        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), [
                'product_type_id' => $activeType->id,
                'craft_id' => $activeCraft->id,
            ])
            ->assertRedirect(route('order-processing.index'));
        $configuration = ProductProcessingCraft::where('product_type_id', $activeType->id)
            ->firstOrFail();

        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), ['product_type_id' => $activeType->id])
            ->assertSessionHasErrors('product_type_id');
        $configuration->delete();
        $this->actingAs($actor, 'admin')
            ->post(route('order-processing.store'), ['product_type_id' => $activeType->id])
            ->assertSessionHasErrors('product_type_id');
    }

    public function test_soft_delete_hides_configuration_retains_pivots_and_navigation_route_works()
    {
        $actor = $this->createActor('artwork_processing');
        $type = ProductType::create(['chinese_name' => '软删除配置类型']);
        $employee = $this->createEmployee('保留关联员工', 'order_processing');
        $configuration = ProductProcessingCraft::create([
            'product_type_id' => $type->id,
            'chinese_name' => $type->chinese_name,
        ]);
        $this->sync($configuration, 'orderProcessorEmployees', 'order_processing', [$employee]);

        $this->actingAs($actor, 'admin')
            ->get(route('order-processing.index'))
            ->assertOk()
            ->assertSee('订单处理配置')
            ->assertSee(route('order-processing.index'), false)
            ->assertSee($type->chinese_name);

        $this->actingAs($actor, 'admin')
            ->delete(route('order-processing.destroy', $configuration))
            ->assertRedirect(route('order-processing.index'));

        $this->actingAs($actor, 'admin')
            ->get(route('order-processing.index'))
            ->assertOk()
            ->assertViewHas('configurations', function ($paginator) {
                return $paginator->total() === 0;
            });
        $this->assertDatabaseHas('product_processing_craft_employee_assignment', [
            'product_processing_craft_id' => $configuration->id,
            'employee_id' => $employee->id,
            'assignment_type' => 'order_processing',
        ]);
    }

    private function createActor($positionCode)
    {
        $this->sequence++;
        $admin = Admin::create([
            'name' => '订单配置账号 '.$this->sequence,
            'email' => 'order-processing-'.$this->sequence.'@example.test',
            'password' => 'test-password',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'name' => $admin->name,
            'admin_id' => $admin->id,
            'is_active' => true,
        ]);
        $employee->positions()->attach($this->positions[$positionCode]->id);

        return $admin;
    }

    private function createEmployee($name, $positionCodes, $isActive = true)
    {
        $employee = Employee::create([
            'name' => $name,
            'is_active' => $isActive,
        ]);
        foreach ((array) $positionCodes as $positionCode) {
            $employee->positions()->attach($this->positions[$positionCode]->id);
        }

        return $employee;
    }

    private function sync(ProductProcessingCraft $configuration, $relation, $type, array $employees)
    {
        $configuration->{$relation}()->sync(collect($employees)
            ->mapWithKeys(function ($employee) use ($type) {
                return [$employee->id => ['assignment_type' => $type]];
            })
            ->all());
    }
}
