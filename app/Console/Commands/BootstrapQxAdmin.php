<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BootstrapQxAdmin extends Command
{
    protected $signature = 'admin:bootstrap-qxadmin';
    protected $description = 'Create or update the initial qxadmin manager account';

    public function handle()
    {
        $password = env('QXADMIN_INITIAL_PASSWORD');

        if (!is_string($password) || trim($password) === '') {
            $password = $this->secret('请输入 qxadmin 初始密码');
        }

        if (!is_string($password) || trim($password) === '') {
            $this->error('qxadmin initial password cannot be blank.');

            return 1;
        }

        $super = Admin::query()
            ->where('name', 'Admin')
            ->where('role', 'super')
            ->where('is_active', true)
            ->first();

        if (!$super) {
            $this->error('Cannot bootstrap qxadmin: active super admin named Admin was not found.');

            return 1;
        }

        DB::transaction(function () use ($password, $super) {
            $admin = Admin::withTrashed()->updateOrCreate(
                ['email' => 'test@qq.com'],
                [
                    'name' => 'qxadmin',
                    'password' => Hash::make($password),
                    'role' => 'manager',
                    'parent_admin_id' => $super->id,
                    'is_active' => true,
                ]
            );

            if ($admin->trashed()) {
                $admin->restore();
            }

            $employee = Employee::withTrashed()->updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'name' => 'qxadmin',
                    'company_name' => '千兴科技',
                    'is_active' => true,
                ]
            );

            if ($employee->trashed()) {
                $employee->restore();
            }
        });

        $this->info('qxadmin bootstrap completed.');

        return 0;
    }
}
