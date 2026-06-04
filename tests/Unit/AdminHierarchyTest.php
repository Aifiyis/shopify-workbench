<?php

namespace Tests\Unit;

use App\Models\Admin;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class AdminHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Run migrations before each test
        $this->artisan('migrate:fresh');
    }

    public function test_super_admin_can_be_created()
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@test.com',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        $this->assertNotNull($admin->id);
        $this->assertEquals('super', $admin->role);
        $this->assertNull($admin->parent_admin_id);
    }

    public function test_manager_can_manage_employees()
    {
        $manager = Admin::create([
            'name' => 'Manager',
            'email' => 'manager2@test.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);

        $employee = Admin::create([
            'name' => 'Employee',
            'email' => 'employee2@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'parent_admin_id' => $manager->id,
            'is_active' => true,
        ]);

        $this->assertTrue($manager->canManage($employee->id));
        $this->assertEquals($manager->subordinates()->count(), 1);
    }

    public function test_super_admin_can_manage_anyone()
    {
        $super = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@test.com',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        $manager = Admin::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'parent_admin_id' => $super->id,
            'is_active' => true,
        ]);

        $this->assertTrue($super->canManage($manager->id));
    }

    public function test_employee_cannot_manage_anyone()
    {
        $employee = Admin::create([
            'name' => 'Employee',
            'email' => 'employee3@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        $other = Admin::create([
            'name' => 'Other',
            'email' => 'other3@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        $this->assertFalse($employee->canManage($other->id));
    }

    public function test_company_name_can_be_stored()
    {
        $admin = Admin::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'company_name' => 'Acme Corp',
            'is_active' => true,
        ]);

        $this->assertEquals('Acme Corp', $admin->company_name);
    }

    public function test_get_subordinate_tree()
    {
        $super = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super4@test.com',
            'password' => Hash::make('password'),
            'role' => 'super',
            'is_active' => true,
        ]);

        $manager = Admin::create([
            'name' => 'Manager',
            'email' => 'manager4@test.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'parent_admin_id' => $super->id,
            'is_active' => true,
        ]);

        $employee = Admin::create([
            'name' => 'Employee',
            'email' => 'employee4@test.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'parent_admin_id' => $manager->id,
            'is_active' => true,
        ]);

        $tree = $super->getSubordinateTree();
        $this->assertEquals(1, $tree->count());
        $this->assertEquals('Manager', $tree[0]->name);
    }
}
