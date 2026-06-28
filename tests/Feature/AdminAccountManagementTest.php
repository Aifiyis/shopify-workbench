<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ShopifyStore;
use App\Services\BusinessDataBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(BusinessDataBackfillService::class)->run();
    }

    public function test_super_can_create_and_update_every_account_role()
    {
        $super = $this->createAdmin('super');

        foreach (['employee', 'manager', 'super'] as $role) {
            $email = 'created-'.$role.'@example.test';

            $this->actingAs($super, 'admin')
                ->post(route('admins.store'), $this->accountPayload($email, $role))
                ->assertRedirect(route('admins.index'));

            $created = Admin::where('email', $email)->firstOrFail();
            $this->assertSame($role, $created->role);
            $this->assertEquals($super->id, $created->parent_admin_id);

            $this->actingAs($super, 'admin')
                ->put(route('admins.update', $created), $this->accountPayload(
                    $email,
                    $role === 'employee' ? 'manager' : 'employee',
                    ['password' => null, 'password_confirmation' => null]
                ))
                ->assertRedirect(route('admins.index'));
        }
    }

    public function test_non_super_permission_holders_manage_only_employee_accounts_across_companies()
    {
        $manager = $this->createAdmin('manager', ['company_name' => '甲公司']);
        $positionActor = $this->createPositionAccountManager();
        $employee = $this->createAdmin('employee', [
            'company_name' => '乙公司',
            'parent_admin_id' => null,
        ]);
        $managerTarget = $this->createAdmin('manager');
        $superTarget = $this->createAdmin('super');

        foreach ([$manager, $positionActor] as $actor) {
            $this->actingAs($actor, 'admin')
                ->get(route('admins.edit', $employee))
                ->assertOk();

            $this->actingAs($actor, 'admin')
                ->put(route('admins.update', $employee), $this->accountPayload(
                    $employee->email,
                    'employee',
                    [
                        'name' => '跨公司员工账号',
                        'password' => null,
                        'password_confirmation' => null,
                    ]
                ))
                ->assertRedirect(route('admins.index'));

            $this->actingAs($actor, 'admin')
                ->get(route('admins.edit', $managerTarget))
                ->assertForbidden();
            $this->actingAs($actor, 'admin')
                ->get(route('admins.edit', $superTarget))
                ->assertForbidden();
        }
    }

    public function test_non_super_cannot_promote_employee_or_create_elevated_role()
    {
        $manager = $this->createAdmin('manager');
        $employee = $this->createAdmin('employee');

        $this->actingAs($manager, 'admin')
            ->put(route('admins.update', $employee), $this->accountPayload(
                $employee->email,
                'manager',
                ['password' => null, 'password_confirmation' => null]
            ))
            ->assertForbidden();

        $this->assertSame('employee', $employee->fresh()->role);

        $this->actingAs($manager, 'admin')
            ->post(route('admins.store'), $this->accountPayload(
                'escalated@example.test',
                'super'
            ))
            ->assertForbidden();

        $this->assertDatabaseMissing('admins', ['email' => 'escalated@example.test']);
    }

    public function test_list_scope_search_and_pagination_are_policy_driven()
    {
        $manager = $this->createAdmin('manager');
        $managerTarget = $this->createAdmin('manager', ['name' => '隐藏管理员']);
        $superTarget = $this->createAdmin('super', ['name' => '隐藏超级管理员']);

        for ($index = 1; $index <= 51; $index++) {
            $this->createAdmin('employee', [
                'name' => '分页员工 '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'company_name' => $index === 51 ? '搜索公司' : '其他公司',
                'parent_admin_id' => $index % 2 === 0 ? $managerTarget->id : null,
            ]);
        }

        $this->actingAs($manager, 'admin')
            ->get(route('admins.index'))
            ->assertOk()
            ->assertViewHas('admins', function ($admins) use ($managerTarget, $superTarget) {
                return $admins->perPage() === 50
                    && $admins->lastPage() === 2
                    && !$admins->contains('id', $managerTarget->id)
                    && !$admins->contains('id', $superTarget->id)
                    && $admins->every(function ($admin) {
                        return $admin->role === 'employee';
                    });
            });

        $this->actingAs($manager, 'admin')
            ->get(route('admins.index', ['search' => '搜索公司']))
            ->assertViewHas('admins', function ($admins) {
                return $admins->total() === 1;
            });

        $this->actingAs($superTarget, 'admin')
            ->get(route('admins.index', ['search' => '隐藏管理员']))
            ->assertViewHas('admins', function ($admins) use ($managerTarget) {
                return $admins->total() === 1 && $admins->first()->is($managerTarget);
            });
    }

    public function test_employee_profile_can_be_linked_relinked_and_unlinked_one_to_one()
    {
        $super = $this->createAdmin('super');
        $first = Employee::create(['name' => '员工甲', 'is_active' => true]);
        $second = Employee::create(['name' => '员工乙', 'is_active' => true]);
        $otherAccount = $this->createAdmin('employee');
        $linkedElsewhere = Employee::create([
            'name' => '已关联员工',
            'admin_id' => $otherAccount->id,
            'is_active' => true,
        ]);

        $this->actingAs($super, 'admin')
            ->post(route('admins.store'), $this->accountPayload(
                'linked@example.test',
                'employee',
                ['employee_id' => $first->id]
            ))
            ->assertRedirect(route('admins.index'));

        $account = Admin::where('email', 'linked@example.test')->firstOrFail();
        $this->assertEquals($account->id, $first->fresh()->admin_id);

        $this->actingAs($super, 'admin')
            ->get(route('admins.edit', $account))
            ->assertViewHas('employeeOptions', function ($employees) use (
                $first,
                $second,
                $linkedElsewhere
            ) {
                return $employees->contains('id', $first->id)
                    && $employees->contains('id', $second->id)
                    && !$employees->contains('id', $linkedElsewhere->id);
            });

        $this->actingAs($super, 'admin')
            ->put(route('admins.update', $account), $this->accountPayload(
                $account->email,
                'employee',
                [
                    'password' => null,
                    'password_confirmation' => null,
                    'employee_id' => $second->id,
                ]
            ))
            ->assertRedirect(route('admins.index'));

        $this->assertNull($first->fresh()->admin_id);
        $this->assertEquals($account->id, $second->fresh()->admin_id);

        $this->actingAs($super, 'admin')
            ->put(route('admins.update', $account), $this->accountPayload(
                $account->email,
                'employee',
                [
                    'password' => null,
                    'password_confirmation' => null,
                    'employee_id' => null,
                ]
            ))
            ->assertRedirect(route('admins.index'));

        $this->assertNull($second->fresh()->admin_id);

        $this->actingAs($super, 'admin')
            ->put(route('admins.update', $account), $this->accountPayload(
                $account->email,
                'employee',
                [
                    'password' => null,
                    'password_confirmation' => null,
                    'employee_id' => $linkedElsewhere->id,
                ]
            ))
            ->assertSessionHasErrors('employee_id');
    }

    public function test_store_access_is_created_and_updated_with_the_account()
    {
        $super = $this->createAdmin('super');
        $firstStore = $this->createStore('甲店铺');
        $secondStore = $this->createStore('乙店铺');

        $this->actingAs($super, 'admin')
            ->post(route('admins.store'), $this->accountPayload(
                'stores@example.test',
                'employee',
                [
                    'store_ids' => [$firstStore->id, $secondStore->id],
                    'access_levels' => [
                        $firstStore->id => 'view',
                        $secondStore->id => 'edit',
                    ],
                ]
            ))
            ->assertRedirect(route('admins.index'));

        $account = Admin::where('email', 'stores@example.test')->firstOrFail();
        $this->assertSame('view', $account->stores()->find($firstStore->id)->pivot->access_level);
        $this->assertSame('edit', $account->stores()->find($secondStore->id)->pivot->access_level);

        $this->actingAs($super, 'admin')
            ->put(route('admins.update', $account), $this->accountPayload(
                $account->email,
                'employee',
                [
                    'password' => null,
                    'password_confirmation' => null,
                    'store_ids' => [$firstStore->id],
                    'access_levels' => [$firstStore->id => 'edit'],
                ]
            ))
            ->assertRedirect(route('admins.index'));

        $this->assertEquals([$firstStore->id], $account->stores()->pluck('shopify_stores.id')->all());
        $this->assertSame('edit', $account->stores()->first()->pivot->access_level);
    }

    public function test_soft_delete_clears_employee_link_blocks_self_delete_and_reserves_email()
    {
        $super = $this->createAdmin('super');
        $target = $this->createAdmin('employee', ['email' => 'reserved@example.test']);
        $employee = Employee::create([
            'name' => '保留员工档案',
            'admin_id' => $target->id,
            'is_active' => true,
        ]);

        $this->actingAs($super, 'admin')
            ->delete(route('admins.destroy', $target))
            ->assertRedirect(route('admins.index'));

        $this->assertSoftDeleted('admins', ['id' => $target->id]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'admin_id' => null,
            'deleted_at' => null,
        ]);

        $this->actingAs($super, 'admin')
            ->post(route('admins.store'), $this->accountPayload(
                'reserved@example.test',
                'employee'
            ))
            ->assertSessionHasErrors([
                'email' => '该邮箱已被使用（包括已删除账号），请更换邮箱。',
            ]);

        $this->actingAs($super, 'admin')
            ->delete(route('admins.destroy', $super))
            ->assertForbidden();

        $this->assertDatabaseHas('admins', ['id' => $super->id, 'deleted_at' => null]);
    }

    public function test_inactive_and_deleted_accounts_cannot_log_in()
    {
        $active = $this->createAdmin('employee', [
            'email' => 'active@example.test',
            'password' => Hash::make('password123'),
        ]);
        $inactive = $this->createAdmin('employee', [
            'email' => 'inactive@example.test',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);
        $deleted = $this->createAdmin('employee', [
            'email' => 'deleted@example.test',
            'password' => Hash::make('password123'),
        ]);
        $deleted->delete();

        foreach ([$inactive, $deleted] as $blocked) {
            $this->post(route('login.post'), [
                'email' => $blocked->email,
                'password' => 'password123',
            ])->assertSessionHasErrors('email');
            $this->assertGuest('admin');
        }

        $this->post(route('login.post'), [
            'email' => $active->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($active, 'admin');
    }

    public function test_deleting_manager_does_not_hide_subordinate_employee_account()
    {
        $super = $this->createAdmin('super');
        $manager = $this->createAdmin('manager');
        $employee = $this->createAdmin('employee', [
            'parent_admin_id' => $manager->id,
            'name' => '仍然可见的员工',
        ]);

        $this->actingAs($super, 'admin')
            ->delete(route('admins.destroy', $manager))
            ->assertRedirect(route('admins.index'));

        $this->actingAs($super, 'admin')
            ->get(route('admins.index'))
            ->assertOk()
            ->assertViewHas('admins', function ($admins) use ($employee) {
                return $admins->contains('id', $employee->id);
            })
            ->assertSee('仍然可见的员工')
            ->assertSee('已删除');
    }

    private function createAdmin($role, array $overrides = [])
    {
        $this->sequence++;

        return Admin::create(array_merge([
            'name' => ucfirst($role).' '.$this->sequence,
            'email' => $role.$this->sequence.'@example.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => true,
        ], $overrides));
    }

    private function createPositionAccountManager()
    {
        $actor = $this->createAdmin('employee');
        $position = Position::create([
            'name' => '账号管理员',
            'code' => 'account_manager_'.$this->sequence,
            'is_active' => true,
        ]);
        $permission = Permission::where('code', 'admin_accounts.manage')->firstOrFail();
        $position->permissions()->attach($permission->id);
        $employee = Employee::create([
            'name' => $actor->name,
            'admin_id' => $actor->id,
            'is_active' => true,
        ]);
        $employee->positions()->attach($position->id);

        return $actor;
    }

    private function createStore($name)
    {
        return ShopifyStore::create([
            'shop_name' => $name,
            'shop_url' => strtolower(urlencode($name)).'.myshopify.com',
            'access_token' => 'test-token',
            'is_active' => true,
        ]);
    }

    private function accountPayload($email, $role, array $overrides = [])
    {
        return array_merge([
            'name' => '测试账号',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => $role,
            'company_name' => '测试公司',
            'is_active' => '1',
            'employee_id' => null,
            'store_ids' => [],
            'access_levels' => [],
        ], $overrides);
    }
}
