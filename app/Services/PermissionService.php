<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function has(Admin $admin, string $permissionCode): bool
    {
        if (!$admin->isActiveAccount()) {
            return false;
        }

        if ($admin->role === 'super') {
            return true;
        }

        $roleHasPermission = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role', $admin->role)
            ->where('permissions.code', $permissionCode)
            ->exists();

        if ($roleHasPermission) {
            return true;
        }

        return $admin->employee()
            ->where('employees.is_active', true)
            ->whereHas('positions', function ($positionQuery) use ($permissionCode) {
                $positionQuery
                    ->where('positions.is_active', true)
                    ->whereHas('permissions', function ($permissionQuery) use ($permissionCode) {
                        $permissionQuery->where('permissions.code', $permissionCode);
                    });
            })
            ->exists();
    }

    public function delegableFor(Admin $admin): Collection
    {
        if (!$admin->isActiveAccount()) {
            return collect();
        }

        $query = Permission::query()
            ->where('is_delegable', true)
            ->where('code', '!=', 'permissions.assign')
            ->orderBy('code');

        if ($admin->role === 'super') {
            return $query->get();
        }

        $permissionIds = DB::table('role_permission')
            ->where('role', $admin->role)
            ->pluck('permission_id')
            ->merge(
                DB::table('position_permission')
                    ->join('positions', 'positions.id', '=', 'position_permission.position_id')
                    ->join('employee_position', 'employee_position.position_id', '=', 'positions.id')
                    ->join('employees', 'employees.id', '=', 'employee_position.employee_id')
                    ->where('employees.admin_id', $admin->id)
                    ->where('employees.is_active', true)
                    ->whereNull('employees.deleted_at')
                    ->where('positions.is_active', true)
                    ->whereNull('positions.deleted_at')
                    ->pluck('position_permission.permission_id')
            )
            ->unique()
            ->values();

        return $query->whereIn('id', $permissionIds)->get();
    }

    public function canManageAccount(
        Admin $actor,
        Admin $target = null,
        string $targetRole = 'employee'
    ): bool {
        if (!$actor->isActiveAccount()) {
            return false;
        }

        if ($actor->role === 'super') {
            return true;
        }

        $role = $target ? $target->role : $targetRole;

        return $role === 'employee' && $this->has($actor, 'admin_accounts.manage');
    }
}
