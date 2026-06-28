<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Services\BusinessDataBackfillService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private $permissionService;

    private $accountSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(BusinessDataBackfillService::class)->run();
        $this->permissionService = app(PermissionService::class);
    }

    public function test_has_combines_role_and_position_permissions_without_a_manager_bypass()
    {
        $manager = $this->createAdmin('manager');
        $advertiser = $this->createAdmin('employee');
        $this->linkPosition($advertiser, 'advertising');

        $this->assertTrue($this->permissionService->has($manager, 'employees.manage'));
        $this->assertTrue($this->permissionService->has($advertiser, 'sku_product_types.manage'));
        $this->assertFalse($this->permissionService->has($advertiser, 'employees.manage'));

        $permissionId = Permission::where('code', 'employees.manage')->value('id');
        DB::table('role_permission')
            ->where('role', 'manager')
            ->where('permission_id', $permissionId)
            ->delete();

        $this->assertFalse($this->permissionService->has($manager, 'employees.manage'));
    }

    public function test_has_denies_inactive_or_deleted_accounts_employees_and_positions()
    {
        $admin = $this->createAdmin('employee');
        $employee = $this->linkPosition($admin, 'advertising');
        $position = Position::where('code', 'advertising')->firstOrFail();

        $admin->is_active = false;
        $admin->save();
        $this->assertFalse($this->permissionService->has($admin, 'sku_product_types.manage'));

        $admin->is_active = true;
        $admin->save();
        $employee->is_active = false;
        $employee->save();
        $this->assertFalse($this->permissionService->has($admin, 'sku_product_types.manage'));

        $employee->is_active = true;
        $employee->save();
        $position->is_active = false;
        $position->save();
        $this->assertFalse($this->permissionService->has($admin, 'sku_product_types.manage'));

        $position->is_active = true;
        $position->save();
        $position->delete();
        $this->assertFalse($this->permissionService->has($admin, 'sku_product_types.manage'));

        $position->restore();
        $employee->delete();
        $this->assertFalse($this->permissionService->has($admin, 'sku_product_types.manage'));

        $admin->delete();
        $this->assertFalse($this->permissionService->has($admin, 'sku_product_types.manage'));
    }

    public function test_delegable_for_uses_possession_and_delegable_flag_in_code_order()
    {
        $advertiser = $this->createAdmin('employee');
        $position = Position::where('code', 'advertising')->firstOrFail();
        $this->linkPosition($advertiser, 'advertising');

        $unpossessed = Permission::create([
            'name' => 'Reports',
            'code' => 'reports.manage',
            'is_delegable' => true,
        ]);
        $nonDelegable = Permission::create([
            'name' => 'Archive',
            'code' => 'archive.manage',
            'is_delegable' => false,
        ]);
        $position->permissions()->attach($nonDelegable->id);
        $assign = Permission::where('code', 'permissions.assign')->firstOrFail();
        $assign->is_delegable = true;
        $assign->save();
        $position->permissions()->attach($assign->id);

        $this->assertSame(
            ['permissions.assign', 'sku_product_types.manage'],
            $this->permissionService->delegableFor($advertiser)->pluck('code')->all()
        );

        $manager = $this->createAdmin('manager');
        $this->assertSame([
            'admin_accounts.manage',
            'employees.manage',
            'order_processing.manage',
            'permissions.assign',
            'positions.manage',
            'processing_crafts.manage',
            'sku_product_types.manage',
        ], $this->permissionService->delegableFor($manager)->pluck('code')->all());

        $super = $this->createAdmin('super');
        $this->assertSame([
            'admin_accounts.manage',
            'employees.manage',
            'order_processing.manage',
            'permissions.assign',
            'positions.manage',
            'processing_crafts.manage',
            'reports.manage',
            'sku_product_types.manage',
        ], $this->permissionService->delegableFor($super)->pluck('code')->all());
        $this->assertTrue($unpossessed->is(
            $this->permissionService->delegableFor($super)->firstWhere('code', 'reports.manage')
        ));

        $super->is_active = false;
        $super->save();
        $this->assertTrue($this->permissionService->delegableFor($super)->isEmpty());

        $super->is_active = true;
        $super->save();
        $super->delete();
        $this->assertTrue($this->permissionService->delegableFor($super)->isEmpty());
    }

    public function test_can_manage_account_limits_non_super_actors_to_employee_targets()
    {
        $actor = $this->createAdmin('employee');
        $permission = Permission::where('code', 'admin_accounts.manage')->firstOrFail();
        $position = Position::create([
            'name' => 'Account administration',
            'code' => 'account_administration',
            'is_active' => true,
        ]);
        $position->permissions()->attach($permission->id);
        $this->linkPosition($actor, 'account_administration');

        $employeeTarget = $this->createAdmin('employee');
        $managerTarget = $this->createAdmin('manager');

        $this->assertTrue($this->permissionService->canManageAccount($actor));
        $this->assertTrue($this->permissionService->canManageAccount(
            $actor,
            $employeeTarget,
            'employee'
        ));
        $this->assertFalse($this->permissionService->canManageAccount(
            $actor,
            $employeeTarget,
            'manager'
        ));
        $this->assertFalse($this->permissionService->canManageAccount(
            $actor,
            $employeeTarget,
            'super'
        ));
        $this->assertFalse($this->permissionService->canManageAccount(
            $actor,
            $managerTarget,
            'employee'
        ));
        $this->assertFalse($this->permissionService->canManageAccount($actor, null, 'super'));

        $manager = $this->createAdmin('manager');
        $this->assertTrue($this->permissionService->canManageAccount($manager, $employeeTarget));
        $this->assertFalse($this->permissionService->canManageAccount($manager, $managerTarget));

        $super = $this->createAdmin('super');
        $this->assertTrue($this->permissionService->canManageAccount($super, $managerTarget));
        $this->assertTrue($this->permissionService->canManageAccount($super, null, 'super'));

        $super->is_active = false;
        $super->save();
        $this->assertFalse($this->permissionService->canManageAccount($super, $employeeTarget));

        $actor->delete();
        $this->assertFalse($this->permissionService->canManageAccount($actor, $employeeTarget));
    }

    private function createAdmin($role)
    {
        $this->accountSequence++;

        return Admin::create([
            'name' => ucfirst($role).' '.$this->accountSequence,
            'email' => $role.$this->accountSequence.'@example.test',
            'password' => 'test-password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function linkPosition(Admin $admin, $positionCode)
    {
        $employee = Employee::create([
            'name' => $admin->name,
            'admin_id' => $admin->id,
            'is_active' => true,
        ]);
        $position = Position::where('code', $positionCode)->firstOrFail();
        $employee->positions()->attach($position->id);

        return $employee;
    }
}
