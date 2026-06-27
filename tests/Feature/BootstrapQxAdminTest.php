<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapQxAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstraps_qxadmin_and_updates_it_idempotently()
    {
        $this->withInitialPassword('test-only-bootstrap-password', function ($password) {
            $super = Admin::create([
                'name' => 'Admin',
                'email' => 'admin@example.test',
                'password' => Hash::make('existing-test-password'),
                'role' => 'super',
                'is_active' => true,
            ]);
            $existingAdmin = Admin::create([
                'name' => 'Old qxadmin',
                'email' => 'test@qq.com',
                'password' => Hash::make('old-test-password'),
                'role' => 'manager',
                'is_active' => false,
            ]);
            $existingEmployee = Employee::create([
                'name' => 'Old qxadmin',
                'company_name' => '旧公司',
                'admin_id' => $existingAdmin->id,
                'is_active' => false,
            ]);
            $existingEmployee->delete();
            $existingAdmin->delete();

            $this->assertSame(0, Artisan::call('admin:bootstrap-qxadmin'));
            $this->assertStringNotContainsString($password, Artisan::output());

            $admin = Admin::where('email', 'test@qq.com')->firstOrFail();
            $employee = Employee::where('admin_id', $admin->id)->firstOrFail();

            $this->assertSame($existingAdmin->id, $admin->id);
            $this->assertSame('qxadmin', $admin->name);
            $this->assertSame('test@qq.com', $admin->email);
            $this->assertSame('manager', $admin->role);
            $this->assertTrue($admin->is_active);
            $this->assertEquals($super->id, $admin->parent_admin_id);
            $this->assertTrue(Hash::check($password, $admin->password));

            $this->assertSame($existingEmployee->id, $employee->id);
            $this->assertSame('qxadmin', $employee->name);
            $this->assertSame('千兴科技', $employee->company_name);
            $this->assertTrue($employee->is_active);

            $adminCount = Admin::withTrashed()->count();
            $employeeCount = Employee::withTrashed()->count();

            $this->assertSame(0, Artisan::call('admin:bootstrap-qxadmin'));
            $this->assertSame($adminCount, Admin::withTrashed()->count());
            $this->assertSame($employeeCount, Employee::withTrashed()->count());
            $this->assertSame(1, Admin::withTrashed()->where('email', 'test@qq.com')->count());
            $this->assertSame(1, Employee::withTrashed()->where('admin_id', $admin->id)->count());
        });
    }

    public function test_fails_clearly_when_active_admin_super_is_missing()
    {
        $this->withInitialPassword('test-only-bootstrap-password', function () {
            $this->artisan('admin:bootstrap-qxadmin')
                ->expectsOutput('Cannot bootstrap qxadmin: active super admin named Admin was not found.')
                ->assertExitCode(1);
            $this->assertSame(0, Admin::withTrashed()->where('email', 'test@qq.com')->count());
        });
    }

    private function withInitialPassword($password, callable $callback)
    {
        putenv('QXADMIN_INITIAL_PASSWORD=' . $password);
        $_ENV['QXADMIN_INITIAL_PASSWORD'] = $password;
        $_SERVER['QXADMIN_INITIAL_PASSWORD'] = $password;

        try {
            return $callback($password);
        } finally {
            putenv('QXADMIN_INITIAL_PASSWORD');
            unset($_ENV['QXADMIN_INITIAL_PASSWORD'], $_SERVER['QXADMIN_INITIAL_PASSWORD']);
        }
    }
}
