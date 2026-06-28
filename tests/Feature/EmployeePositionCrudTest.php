<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmployeePositionCrudTest extends TestCase
{
    use RefreshDatabase;

    private $employeesManage;
    private $positionsManage;
    private $permissionsAssign;
    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeesManage = $this->createPermission(
            'employees.manage',
            '员工档案管理'
        );
        $this->positionsManage = $this->createPermission(
            'positions.manage',
            '职位管理'
        );
        $this->permissionsAssign = $this->createPermission(
            'permissions.assign',
            '权限分配',
            false
        );
    }

    public function test_manager_scope_is_limited_to_direct_subordinates_in_queries_and_actions()
    {
        $this->grantRolePermission('manager', $this->employeesManage);
        $manager = $this->createAdmin('manager');
        $managerEmployee = $this->linkEmployee($manager, '主管本人');
        $direct = Employee::create([
            'name' => '直属员工',
            'supervisor_id' => $managerEmployee->id,
            'is_active' => true,
        ]);
        $unrelated = Employee::create([
            'name' => '非直属员工',
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('employees.index'))
            ->assertOk()
            ->assertViewHas('employees', function ($employees) use ($direct, $unrelated) {
                return $employees->contains('id', $direct->id)
                    && !$employees->contains('id', $unrelated->id);
            });

        $this->actingAs($manager, 'admin')
            ->get(route('employees.edit', $direct))
            ->assertOk();
        $this->actingAs($manager, 'admin')
            ->get(route('employees.edit', $unrelated))
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->put(route('employees.update', $direct), [
                'name' => '试图改组',
                'company_name' => '千兴科技',
                'supervisor_id' => $unrelated->id,
                'is_active' => '1',
                'position_ids' => [],
            ])
            ->assertSessionHasErrors('supervisor_id');

        $this->actingAs($manager, 'admin')
            ->post(route('employees.store'), [
                'name' => '新直属员工',
                'company_name' => '新公司',
                'is_active' => '1',
                'position_ids' => [],
            ])
            ->assertRedirect(route('employees.index'));

        $created = Employee::where('name', '新直属员工')->firstOrFail();
        $this->assertEquals($managerEmployee->id, $created->supervisor_id);

        $managerEmployee->update(['is_active' => false]);
        $this->actingAs($manager, 'admin')
            ->get(route('employees.create'))
            ->assertForbidden();
        $this->actingAs($manager, 'admin')
            ->get(route('employees.index'))
            ->assertForbidden();
    }

    public function test_explicit_hr_permission_has_global_scope_and_super_can_manage_all_employees()
    {
        $hr = $this->createAdmin('employee');
        $hrPosition = Position::create([
            'name' => 'HR',
            'code' => 'hr',
            'is_active' => true,
        ]);
        $hrPosition->permissions()->attach($this->employeesManage->id);
        $this->linkEmployee($hr, '人事专员')->positions()->attach($hrPosition->id);

        $employeeA = Employee::create(['name' => '公司 A 员工', 'is_active' => true]);
        $employeeB = Employee::create(['name' => '公司 B 员工', 'is_active' => true]);

        $this->actingAs($hr, 'admin')
            ->get(route('employees.index'))
            ->assertOk()
            ->assertViewHas('employees', function ($employees) use ($employeeA, $employeeB) {
                return $employees->contains('id', $employeeA->id)
                    && $employees->contains('id', $employeeB->id);
            });
        $this->actingAs($hr, 'admin')
            ->get(route('employees.edit', $employeeB))
            ->assertOk();

        $super = $this->createAdmin('super');
        $this->actingAs($super, 'admin')
            ->get(route('employees.index'))
            ->assertOk()
            ->assertViewHas('employees', function ($employees) use ($employeeA, $employeeB) {
                return $employees->contains('id', $employeeA->id)
                    && $employees->contains('id', $employeeB->id);
            });
    }

    public function test_employee_create_update_company_options_accounts_and_multiple_positions()
    {
        $super = $this->createAdmin('super');
        $account = $this->createAdmin('employee');
        $otherAccount = $this->createAdmin('employee');
        $positionA = $this->createPosition('广告', 'advertising');
        $positionB = $this->createPosition('运营', 'operations');
        Employee::create([
            'name' => '现有员工',
            'company_name' => '现有公司',
            'admin_id' => $otherAccount->id,
            'is_active' => true,
        ]);

        $this->actingAs($super, 'admin')
            ->post(route('employees.store'), [
                'name' => '张三',
                'company_name' => '新公司',
                'admin_id' => $account->id,
                'is_active' => '1',
                'position_ids' => [$positionA->id, $positionB->id],
            ])
            ->assertRedirect(route('employees.index'));

        $employee = Employee::where('name', '张三')->firstOrFail();
        $this->assertSame('新公司', $employee->company_name);
        $this->assertEquals($account->id, $employee->admin_id);
        $this->assertEqualsCanonicalizing(
            [$positionA->id, $positionB->id],
            $employee->positions()->pluck('positions.id')->all()
        );

        $this->actingAs($super, 'admin')
            ->get(route('employees.index'))
            ->assertOk()
            ->assertViewHas('companyNames', function ($companies) {
                return $companies->contains('现有公司')
                    && $companies->contains('新公司');
            });

        $this->actingAs($super, 'admin')
            ->put(route('employees.update', $employee), [
                'name' => '张三更新',
                'company_name' => '现有公司',
                'admin_id' => $account->id,
                'is_active' => '0',
                'position_ids' => [$positionB->id],
            ])
            ->assertRedirect(route('employees.index'));

        $employee->refresh();
        $this->assertSame('张三更新', $employee->name);
        $this->assertFalse($employee->is_active);
        $this->assertEquals([$positionB->id], $employee->positions()->pluck('positions.id')->all());

        $this->actingAs($super, 'admin')
            ->post(route('employees.store'), [
                'name' => '重复账号',
                'admin_id' => $account->id,
                'is_active' => '1',
                'position_ids' => [],
            ])
            ->assertSessionHasErrors('admin_id');
    }

    public function test_historical_inactive_or_deleted_positions_can_remain_but_cannot_be_newly_assigned()
    {
        $super = $this->createAdmin('super');
        $inactive = $this->createPosition('停用职位', 'inactive_position', false);
        $deleted = $this->createPosition('已删除职位', 'deleted_position');
        $deleted->delete();
        $active = $this->createPosition('在用职位', 'active_position');
        $employee = Employee::create(['name' => '历史员工', 'is_active' => true]);
        $employee->positions()->attach([$inactive->id, $deleted->id]);

        $this->actingAs($super, 'admin')
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertViewHas('positionOptions', function ($positions) use ($inactive, $deleted, $active) {
                return $positions->contains('id', $inactive->id)
                    && $positions->contains('id', $deleted->id)
                    && $positions->contains('id', $active->id);
            });

        $this->actingAs($super, 'admin')
            ->put(route('employees.update', $employee), [
                'name' => '历史员工',
                'is_active' => '1',
                'position_ids' => [$inactive->id, $deleted->id, $active->id],
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertEqualsCanonicalizing(
            [$inactive->id, $deleted->id, $active->id],
            DB::table('employee_position')->where('employee_id', $employee->id)->pluck('position_id')->all()
        );

        $other = Employee::create(['name' => '其他员工', 'is_active' => true]);
        $this->actingAs($super, 'admin')
            ->put(route('employees.update', $other), [
                'name' => '其他员工',
                'is_active' => '1',
                'position_ids' => [$inactive->id, $deleted->id],
            ])
            ->assertSessionHasErrors('position_ids.0');
    }

    public function test_employee_delete_is_soft_preserves_pivots_and_rejects_own_profile()
    {
        $super = $this->createAdmin('super');
        $position = $this->createPosition('测试职位', 'test_position');
        $employee = Employee::create(['name' => '待删除员工', 'is_active' => true]);
        $employee->positions()->attach($position->id);

        $this->actingAs($super, 'admin')
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'));

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employee_position', [
            'employee_id' => $employee->id,
            'position_id' => $position->id,
        ]);

        $ownProfile = $this->linkEmployee($super, '超管本人');
        $this->actingAs($super, 'admin')
            ->delete(route('employees.destroy', $ownProfile))
            ->assertForbidden();
        $this->assertDatabaseHas('employees', [
            'id' => $ownProfile->id,
            'deleted_at' => null,
        ]);
    }

    public function test_employee_and_position_lists_search_and_paginate_fifty()
    {
        $super = $this->createAdmin('super');
        $permission = $this->createPermission('reports.view', '查看报表');

        for ($index = 1; $index <= 51; $index++) {
            Employee::create([
                'name' => '分页员工 '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'company_name' => $index === 51 ? '搜索公司' : '默认公司',
                'is_active' => $index % 2 === 0,
            ]);
            $position = $this->createPosition(
                '分页职位 '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'page_position_'.$index,
                $index % 2 === 0
            );
            if ($index === 51) {
                $position->permissions()->attach($permission->id);
            }
        }

        $this->actingAs($super, 'admin')
            ->get(route('employees.index'))
            ->assertViewHas('employees', function ($employees) {
                return $employees->perPage() === 50 && $employees->lastPage() === 2;
            });
        $this->actingAs($super, 'admin')
            ->get(route('employees.index', ['search' => '搜索公司']))
            ->assertViewHas('employees', function ($employees) {
                return $employees->total() === 1;
            });

        $this->actingAs($super, 'admin')
            ->get(route('positions.index'))
            ->assertViewHas('positions', function ($positions) {
                return $positions->perPage() === 50 && $positions->lastPage() === 2;
            });
        $this->actingAs($super, 'admin')
            ->get(route('positions.index', ['search' => '查看报表']))
            ->assertViewHas('positions', function ($positions) {
                return $positions->total() === 1;
            });
    }

    public function test_position_crud_uses_stable_codes_and_soft_delete_preserves_permissions()
    {
        $super = $this->createAdmin('super');
        $delegable = $this->createPermission('orders.view', '查看订单');

        $this->actingAs($super, 'admin')
            ->post(route('positions.store'), [
                'name' => '订单审核',
                'code' => 'order_review',
                'is_active' => '1',
                'permission_ids' => [$delegable->id],
            ])
            ->assertRedirect(route('positions.index'));

        $position = Position::where('code', 'order_review')->firstOrFail();
        $this->assertEquals([$delegable->id], $position->permissions()->pluck('permissions.id')->all());

        $this->actingAs($super, 'admin')
            ->put(route('positions.update', $position), [
                'name' => '订单终审',
                'code' => 'changed_code',
                'is_active' => '0',
                'permission_ids' => [$delegable->id],
            ])
            ->assertRedirect(route('positions.index'));

        $position->refresh();
        $this->assertSame('order_review', $position->code);
        $this->assertSame('订单终审', $position->name);
        $this->assertFalse($position->is_active);

        $this->actingAs($super, 'admin')
            ->delete(route('positions.destroy', $position))
            ->assertRedirect(route('positions.index'));

        $this->assertSoftDeleted('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('position_permission', [
            'position_id' => $position->id,
            'permission_id' => $delegable->id,
        ]);
    }

    public function test_unauthorized_permission_delegation_returns_validation_error()
    {
        $actor = $this->createAdmin('employee');
        $allowed = $this->createPermission('orders.edit', '编辑订单');
        $unauthorized = $this->createPermission('finance.manage', '财务管理');
        $actorPosition = $this->createPosition('职位管理员', 'position_admin');
        $actorPosition->permissions()->attach([
            $this->positionsManage->id,
            $this->permissionsAssign->id,
            $allowed->id,
        ]);
        $this->linkEmployee($actor, '职位管理员')->positions()->attach($actorPosition->id);

        $this->actingAs($actor, 'admin')
            ->post(route('positions.store'), [
                'name' => '未授权职位',
                'code' => 'unauthorized_position',
                'is_active' => '1',
                'permission_ids' => [$allowed->id, $unauthorized->id],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('permission_ids.1');

        $this->assertDatabaseMissing('positions', ['code' => 'unauthorized_position']);
    }

    public function test_position_update_preserves_locked_permissions_and_displays_them_read_only()
    {
        $actor = $this->createAdmin('employee');
        $delegable = $this->createPermission('orders.edit', '编辑订单');
        $locked = $this->createPermission('finance.locked', '财务锁定', false);
        $actorPosition = $this->createPosition('职位管理员', 'position_admin');
        $actorPosition->permissions()->attach([
            $this->positionsManage->id,
            $this->permissionsAssign->id,
            $delegable->id,
        ]);
        $this->linkEmployee($actor, '职位管理员')->positions()->attach($actorPosition->id);

        $target = $this->createPosition('目标职位', 'target_position');
        $target->permissions()->attach([$delegable->id, $locked->id]);

        $this->actingAs($actor, 'admin')
            ->get(route('positions.edit', $target))
            ->assertOk()
            ->assertSee('不可分配（保留）')
            ->assertSee($locked->name);

        $this->actingAs($actor, 'admin')
            ->put(route('positions.update', $target), [
                'name' => '目标职位更新',
                'is_active' => '1',
                'permission_ids' => [],
            ])
            ->assertRedirect(route('positions.index'));

        $this->assertEqualsCanonicalizing(
            [$locked->id],
            $target->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_routes_tabs_and_navigation_are_available_in_chinese()
    {
        $this->assertTrue(Route::has('employees.index'));
        $this->assertTrue(Route::has('employees.create'));
        $this->assertTrue(Route::has('positions.index'));
        $this->assertTrue(Route::has('positions.create'));

        $super = $this->createAdmin('super');
        $this->actingAs($super, 'admin')
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('员工与职位')
            ->assertSee('员工档案')
            ->assertSee('职位权限');
        $this->actingAs($super, 'admin')
            ->get(route('positions.index'))
            ->assertOk()
            ->assertSee('员工档案')
            ->assertSee('职位权限');
    }

    private function createAdmin($role)
    {
        $this->sequence++;

        return Admin::create([
            'name' => ucfirst($role).' '.$this->sequence,
            'email' => $role.$this->sequence.'@example.test',
            'password' => 'test-password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function linkEmployee(Admin $admin, $name)
    {
        return Employee::create([
            'name' => $name,
            'admin_id' => $admin->id,
            'is_active' => true,
        ]);
    }

    private function createPosition($name, $code, $active = true)
    {
        return Position::create([
            'name' => $name,
            'code' => $code,
            'is_active' => $active,
        ]);
    }

    private function createPermission($code, $name, $delegable = true)
    {
        return Permission::create([
            'name' => $name,
            'code' => $code,
            'is_delegable' => $delegable,
        ]);
    }

    private function grantRolePermission($role, Permission $permission)
    {
        DB::table('role_permission')->insert([
            'role' => $role,
            'permission_id' => $permission->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
