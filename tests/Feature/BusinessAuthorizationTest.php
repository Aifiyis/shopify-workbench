<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use App\Policies\AdminPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\PositionPolicy;
use App\Policies\ProcessingCraftNodePolicy;
use App\Policies\ProductProcessingCraftPolicy;
use App\Policies\ProductTypePolicy;
use App\Policies\SkuMatchProductTypePolicy;
use App\Services\BusinessDataBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BusinessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private $accountSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(BusinessDataBackfillService::class)->run();
    }

    public function test_active_accounts_can_view_business_records_and_inactive_or_deleted_accounts_cannot()
    {
        $activeAccounts = [
            $this->createAdmin('employee'),
            $this->createAdmin('manager'),
            $this->createAdmin('super'),
        ];
        $inactive = $this->createAdmin('employee', false);
        $deleted = $this->createAdmin('employee');
        $deleted->delete();

        $records = $this->createBusinessRecords();

        foreach ($records as $modelClass => $record) {
            foreach ($activeAccounts as $active) {
                $this->assertTrue(Gate::forUser($active)->allows('viewAny', $modelClass));
                $this->assertTrue(Gate::forUser($active)->allows('view', $record));
            }
            $this->assertFalse(Gate::forUser($inactive)->allows('viewAny', $modelClass));
            $this->assertFalse(Gate::forUser($inactive)->allows('view', $record));
            $this->assertFalse(Gate::forUser($deleted)->allows('viewAny', $modelClass));
            $this->assertFalse(Gate::forUser($deleted)->allows('view', $record));
        }
    }

    public function test_advertising_and_operations_positions_manage_sku_mappings_and_product_types()
    {
        $records = $this->createBusinessRecords();

        foreach (['advertising', 'operations'] as $positionCode) {
            $actor = $this->createAdmin('employee');
            $this->linkPosition($actor, $positionCode);

            foreach ([
                SkuMatchProductType::class => $records[SkuMatchProductType::class],
                ProductType::class => $records[ProductType::class],
            ] as $modelClass => $record) {
                $this->assertTrue(Gate::forUser($actor)->allows('create', $modelClass));
                $this->assertTrue(Gate::forUser($actor)->allows('update', $record));
                $this->assertTrue(Gate::forUser($actor)->allows('delete', $record));
            }

            $this->assertFalse(Gate::forUser($actor)->allows(
                'create',
                ProductProcessingCraft::class
            ));
            $this->assertFalse(Gate::forUser($actor)->allows(
                'create',
                ProcessingCraftNode::class
            ));
        }
    }

    public function test_processing_positions_manage_order_processing_and_craft_hierarchy()
    {
        $records = $this->createBusinessRecords();

        foreach (['procurement', 'order_processing', 'artwork_processing'] as $positionCode) {
            $actor = $this->createAdmin('employee');
            $this->linkPosition($actor, $positionCode);

            foreach ([
                ProductProcessingCraft::class => $records[ProductProcessingCraft::class],
                ProcessingCraftNode::class => $records[ProcessingCraftNode::class],
            ] as $modelClass => $record) {
                $this->assertTrue(Gate::forUser($actor)->allows('create', $modelClass));
                $this->assertTrue(Gate::forUser($actor)->allows('update', $record));
                $this->assertTrue(Gate::forUser($actor)->allows('delete', $record));
            }

            $this->assertFalse(Gate::forUser($actor)->allows(
                'create',
                SkuMatchProductType::class
            ));
        }
    }

    public function test_seeded_manager_permissions_authorize_staff_positions_and_delegation()
    {
        $manager = $this->createAdmin('manager');
        $managerEmployee = Employee::create([
            'name' => 'Manager employee',
            'admin_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'name' => 'Direct employee',
            'supervisor_id' => $managerEmployee->id,
            'is_active' => true,
        ]);
        $unrelatedEmployee = Employee::create([
            'name' => 'Unrelated employee',
            'is_active' => true,
        ]);
        $position = Position::where('code', 'advertising')->firstOrFail();

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue(Gate::forUser($manager)->allows($ability, Employee::class));
            $this->assertTrue(Gate::forUser($manager)->allows($ability, Position::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($manager)->allows($ability, $employee));
            $this->assertFalse(Gate::forUser($manager)->allows($ability, $unrelatedEmployee));
            $this->assertTrue(Gate::forUser($manager)->allows($ability, $position));
        }
        $this->assertTrue(Gate::forUser($manager)->allows('assignPermissions', $position));

        $permissionsAssignId = Permission::where('code', 'permissions.assign')->value('id');
        DB::table('role_permission')
            ->where('role', 'manager')
            ->where('permission_id', $permissionsAssignId)
            ->delete();

        $this->assertFalse(Gate::forUser($manager)->allows('assignPermissions', $position));
    }

    public function test_admin_account_policy_limits_non_super_actors_to_employee_targets()
    {
        $manager = $this->createAdmin('manager');
        $employeeTarget = $this->createAdmin('employee');
        $managerTarget = $this->createAdmin('manager');
        $superTarget = $this->createAdmin('super');

        $this->assertTrue(Gate::forUser($manager)->allows('viewAny', Admin::class));
        $this->assertTrue(Gate::forUser($manager)->allows(
            'create',
            [Admin::class, 'employee']
        ));
        $this->assertFalse(Gate::forUser($manager)->allows(
            'create',
            [Admin::class, 'manager']
        ));

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($manager)->allows($ability, $employeeTarget));
            $this->assertFalse(Gate::forUser($manager)->allows($ability, $managerTarget));
            $this->assertFalse(Gate::forUser($manager)->allows($ability, $superTarget));
        }

        $positionActor = $this->createAdmin('employee');
        $position = Position::create([
            'name' => 'Account administration',
            'code' => 'account_administration',
            'is_active' => true,
        ]);
        $permissionId = Permission::where('code', 'admin_accounts.manage')->value('id');
        $position->permissions()->attach($permissionId);
        $this->linkPosition($positionActor, 'account_administration');

        $this->assertTrue(Gate::forUser($positionActor)->allows('update', $employeeTarget));
        $this->assertFalse(Gate::forUser($positionActor)->allows('update', $managerTarget));
    }

    public function test_admin_update_authorization_checks_current_and_proposed_roles()
    {
        $manager = $this->createAdmin('manager');
        $employeeTarget = $this->createAdmin('employee');

        $this->assertTrue(Gate::forUser($manager)->allows(
            'update',
            [$employeeTarget, 'employee']
        ));
        $this->assertFalse(Gate::forUser($manager)->allows(
            'update',
            [$employeeTarget, 'manager']
        ));
        $this->assertFalse(Gate::forUser($manager)->allows(
            'update',
            [$employeeTarget, 'super']
        ));

        $activeSuper = $this->createAdmin('super');
        $managerTarget = $this->createAdmin('manager');
        $superTarget = $this->createAdmin('super');

        $this->assertTrue(Gate::forUser($activeSuper)->allows(
            'update',
            [$managerTarget, 'super']
        ));
        $this->assertTrue(Gate::forUser($activeSuper)->allows(
            'update',
            [$superTarget, 'manager']
        ));
    }

    public function test_only_active_non_deleted_super_can_manage_manager_and_super_accounts()
    {
        $activeSuper = $this->createAdmin('super');
        $inactiveSuper = $this->createAdmin('super', false);
        $deletedSuper = $this->createAdmin('super');
        $deletedSuper->delete();
        $managerTarget = $this->createAdmin('manager');
        $superTarget = $this->createAdmin('super');

        foreach ([$managerTarget, $superTarget] as $target) {
            $this->assertTrue(Gate::forUser($activeSuper)->allows('update', $target));
            $this->assertFalse(Gate::forUser($inactiveSuper)->allows('update', $target));
            $this->assertFalse(Gate::forUser($deletedSuper)->allows('update', $target));
        }

        $this->assertTrue(Gate::forUser($activeSuper)->allows(
            'create',
            [Admin::class, 'super']
        ));
        $this->assertFalse(Gate::forUser($inactiveSuper)->allows(
            'create',
            [Admin::class, 'manager']
        ));
    }

    public function test_all_business_policy_mappings_are_registered()
    {
        $expected = [
            SkuMatchProductType::class => SkuMatchProductTypePolicy::class,
            ProductType::class => ProductTypePolicy::class,
            ProductProcessingCraft::class => ProductProcessingCraftPolicy::class,
            ProcessingCraftNode::class => ProcessingCraftNodePolicy::class,
            Employee::class => EmployeePolicy::class,
            Position::class => PositionPolicy::class,
            Admin::class => AdminPolicy::class,
        ];

        foreach ($expected as $modelClass => $policyClass) {
            $this->assertInstanceOf($policyClass, Gate::getPolicyFor($modelClass));
        }
    }

    private function createBusinessRecords()
    {
        $productType = ProductType::create(['chinese_name' => 'Embroidery']);
        $processingCraft = ProductProcessingCraft::create([
            'chinese_name' => 'Embroidery',
            'product_type_id' => $productType->id,
        ]);
        $skuMatch = SkuMatchProductType::create([
            'original_sku' => 'AUTH-RAW-1',
            'cleaned_sku' => 'AUTH-CLEAN-1',
            'chinese_name' => 'Embroidery',
            'product_type_id' => $productType->id,
        ]);

        return [
            SkuMatchProductType::class => $skuMatch,
            ProductType::class => $productType,
            ProductProcessingCraft::class => $processingCraft,
            ProcessingCraftNode::class => ProcessingCraftNode::create([
                'name' => 'Embroidery',
                'path' => 'Embroidery',
            ]),
        ];
    }

    private function createAdmin($role, $isActive = true)
    {
        $this->accountSequence++;

        return Admin::create([
            'name' => ucfirst($role).' '.$this->accountSequence,
            'email' => $role.$this->accountSequence.'@example.test',
            'password' => 'test-password',
            'role' => $role,
            'is_active' => $isActive,
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
