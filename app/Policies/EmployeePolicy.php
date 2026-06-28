<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Employee;
use App\Services\PermissionService;

class EmployeePolicy
{
    private $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function viewAny(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'employees.manage');
    }

    public function view(Admin $actor, Employee $employee): bool
    {
        return $this->permissions->has($actor, 'employees.manage');
    }

    public function create(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'employees.manage');
    }

    public function update(Admin $actor, Employee $employee): bool
    {
        return $this->permissions->has($actor, 'employees.manage');
    }

    public function delete(Admin $actor, Employee $employee): bool
    {
        return $this->permissions->has($actor, 'employees.manage');
    }
}
