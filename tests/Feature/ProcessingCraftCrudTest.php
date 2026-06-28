<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessingCraftCrudTest extends TestCase
{
    use RefreshDatabase;

    private $positions = [];
    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $craftPermission = Permission::create([
            'name' => '工艺层级管理',
            'code' => 'processing_crafts.manage',
            'is_delegable' => true,
        ]);
        $orderPermission = Permission::create([
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
                $position->permissions()->attach([
                    $craftPermission->id,
                    $orderPermission->id,
                ]);
            }
            $this->positions[$code] = $position;
        }
    }

    public function test_active_employee_can_search_crafts_and_soft_deleted_crafts_are_hidden()
    {
        $viewer = $this->createActor('finance');
        $root = ProcessingCraftNode::create([
            'name' => '印花',
            'path' => '印花',
        ]);
        $child = ProcessingCraftNode::create([
            'parent_id' => $root->id,
            'name' => '热转印',
            'path' => '印花-热转印',
        ]);
        $deleted = ProcessingCraftNode::create([
            'name' => '已删除工艺',
            'path' => '已删除工艺',
        ]);
        $deleted->delete();

        $this->actingAs($viewer, 'admin')
            ->get(route('processing-crafts.index', ['search' => '热转']))
            ->assertOk()
            ->assertViewHas('crafts', function ($crafts) use ($child) {
                return $crafts->total() === 1 && $crafts->first()->is($child);
            })
            ->assertSee('印花-热转印')
            ->assertDontSee('已删除工艺')
            ->assertDontSee('节点');
    }

    public function test_authorized_actor_creates_and_moves_crafts_with_generated_descendant_paths()
    {
        $actor = $this->createActor('order_processing');

        $this->actingAs($actor, 'admin')
            ->get(route('processing-crafts.create', [
                'return_to' => 'order-processing.create',
            ]))
            ->assertOk()
            ->assertSee('新增工艺')
            ->assertSee('return_to=order-processing.create', false);

        $this->actingAs($actor, 'admin')
            ->post(route('processing-crafts.store'), ['name' => '服装'])
            ->assertRedirect(route('processing-crafts.index'));
        $root = ProcessingCraftNode::where('path', '服装')->firstOrFail();

        $this->actingAs($actor, 'admin')
            ->post(route('processing-crafts.store'), [
                'parent_id' => $root->id,
                'name' => '刺绣',
            ])
            ->assertRedirect(route('processing-crafts.index'));
        $child = ProcessingCraftNode::where('path', '服装-刺绣')->firstOrFail();

        $this->actingAs($actor, 'admin')
            ->get(route('processing-crafts.edit', $child))
            ->assertOk()
            ->assertSee('编辑工艺')
            ->assertSee('服装');

        ProcessingCraftNode::create([
            'parent_id' => $child->id,
            'name' => '左胸',
            'path' => '服装-刺绣-左胸',
        ]);

        $this->actingAs($actor, 'admin')
            ->put(route('processing-crafts.update', $root), ['name' => '成衣'])
            ->assertRedirect(route('processing-crafts.index'));

        $this->assertDatabaseHas('processing_craft_nodes', [
            'id' => $root->id,
            'path' => '成衣',
        ]);
        $this->assertDatabaseHas('processing_craft_nodes', [
            'id' => $child->id,
            'path' => '成衣-刺绣',
        ]);
        $this->assertDatabaseHas('processing_craft_nodes', [
            'name' => '左胸',
            'path' => '成衣-刺绣-左胸',
        ]);
    }

    public function test_update_rejects_self_or_descendant_as_parent()
    {
        $actor = $this->createActor('artwork_processing');
        $root = $this->createCraft('彩图');
        $child = $this->createCraft('描边', $root);
        $grandchild = $this->createCraft('白边', $child);

        foreach ([$root, $child, $grandchild] as $parent) {
            $this->actingAs($actor, 'admin')
                ->put(route('processing-crafts.update', $root), [
                    'name' => $root->name,
                    'parent_id' => $parent->id,
                ])
                ->assertSessionHasErrors('parent_id');
        }

        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame('彩图', $root->fresh()->path);
    }

    public function test_quick_create_returns_select_payload_and_chinese_json_validation_errors()
    {
        $actor = $this->createActor('procurement');
        $parent = $this->createCraft('包装');

        $response = $this->actingAs($actor, 'admin')
            ->postJson(route('processing-crafts.quick-store'), [
                'parent_id' => $parent->id,
                'name' => '礼盒',
            ])
            ->assertOk();

        $craft = ProcessingCraftNode::where('path', '包装-礼盒')->firstOrFail();
        $response->assertExactJson([
            'id' => $craft->id,
            'name' => '礼盒',
            'path' => '包装-礼盒',
            'depth' => 1,
        ]);

        $this->actingAs($actor, 'admin')
            ->postJson(route('processing-crafts.quick-store'), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name')
            ->assertJsonFragment(['请输入工艺名称。']);

        $craft->delete();
        $this->actingAs($actor, 'admin')
            ->postJson(route('processing-crafts.quick-store'), [
                'parent_id' => $parent->id,
                'name' => '礼盒',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name')
            ->assertJsonFragment(['该工艺已存在，包括已删除的记录。']);
    }

    public function test_deletion_is_blocked_by_active_or_deleted_children_and_references()
    {
        $actor = $this->createActor('order_processing');
        $withActiveChild = $this->createCraft('活动子工艺父级');
        $this->createCraft('活动子工艺', $withActiveChild);

        $withDeletedChild = $this->createCraft('删除子工艺父级');
        $deletedChild = $this->createCraft('删除子工艺', $withDeletedChild);
        $deletedChild->delete();

        $withActiveReference = $this->createCraft('活动引用工艺');
        ProductProcessingCraft::create([
            'chinese_name' => '活动引用配置',
            'craft_id' => $withActiveReference->id,
        ]);

        $withDeletedReference = $this->createCraft('删除引用工艺');
        $deletedReference = ProductProcessingCraft::create([
            'chinese_name' => '删除引用配置',
            'craft_id' => $withDeletedReference->id,
        ]);
        $deletedReference->delete();

        foreach ([
            $withActiveChild,
            $withDeletedChild,
            $withActiveReference,
            $withDeletedReference,
        ] as $craft) {
            $this->actingAs($actor, 'admin')
                ->delete(route('processing-crafts.destroy', $craft))
                ->assertRedirect(route('processing-crafts.index'))
                ->assertSessionHas('error');
            $this->assertFalse($craft->fresh()->trashed());
        }

        $unreferenced = $this->createCraft('可删除工艺');
        $this->actingAs($actor, 'admin')
            ->delete(route('processing-crafts.destroy', $unreferenced))
            ->assertRedirect(route('processing-crafts.index'));
        $this->assertSoftDeleted('processing_craft_nodes', ['id' => $unreferenced->id]);
    }

    public function test_active_viewer_cannot_mutate_crafts_without_permission()
    {
        $viewer = $this->createActor('finance');
        $craft = $this->createCraft('权限工艺');

        $this->actingAs($viewer, 'admin')
            ->get(route('processing-crafts.index'))
            ->assertOk();
        $this->actingAs($viewer, 'admin')
            ->post(route('processing-crafts.store'), ['name' => '拒绝创建'])
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->postJson(route('processing-crafts.quick-store'), ['name' => '拒绝快捷创建'])
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->put(route('processing-crafts.update', $craft), ['name' => '拒绝更新'])
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->delete(route('processing-crafts.destroy', $craft))
            ->assertForbidden();
    }

    public function test_management_page_only_accepts_safe_order_processing_return_targets()
    {
        $viewer = $this->createActor('finance');

        $this->actingAs($viewer, 'admin')
            ->get(route('processing-crafts.index', [
                'return_to' => 'order-processing.create',
            ]))
            ->assertOk()
            ->assertSee('返回订单处理配置')
            ->assertSee(route('order-processing.create'), false);

        $localTarget = '/order-processing/create?from=crafts';
        $this->actingAs($viewer, 'admin')
            ->get(route('processing-crafts.index', ['return_to' => $localTarget]))
            ->assertOk()
            ->assertSee(url($localTarget), false);

        foreach (['https://evil.example/steal', '//evil.example/steal', '/dashboard'] as $target) {
            $this->actingAs($viewer, 'admin')
                ->get(route('processing-crafts.index', ['return_to' => $target]))
                ->assertOk()
                ->assertDontSee('返回订单处理配置')
                ->assertDontSee('evil.example');
        }
    }

    public function test_order_processing_form_has_hierarchical_selector_quick_create_and_management_link()
    {
        $actor = $this->createActor('order_processing');
        $root = $this->createCraft('服饰');
        $this->createCraft('烫画', $root);

        $this->actingAs($actor, 'admin')
            ->get(route('order-processing.create'))
            ->assertOk()
            ->assertSee('data-searchable-select', false)
            ->assertSee('data-depth="1"', false)
            ->assertSee('新建工艺')
            ->assertSee('管理工艺')
            ->assertSee('window.AdminUI.addSelectOption', false)
            ->assertSee(route('processing-crafts.quick-store'), false)
            ->assertSee(route('processing-crafts.index'), false)
            ->assertDontSee('节点');
    }

    private function createCraft($name, ProcessingCraftNode $parent = null)
    {
        return ProcessingCraftNode::create([
            'parent_id' => $parent ? $parent->id : null,
            'name' => $name,
            'path' => $parent ? $parent->path.'-'.$name : $name,
        ]);
    }

    private function createActor($positionCode)
    {
        $this->sequence++;
        $admin = Admin::create([
            'name' => '工艺测试账号 '.$this->sequence,
            'email' => 'craft-test-'.$this->sequence.'@example.test',
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
}
