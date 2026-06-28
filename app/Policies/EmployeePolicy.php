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
        return $this->canManageEmployees($actor);
    }

    public function view(Admin $actor, Employee $employee): bool
    {
        return $this->canManageEmployee($actor, $employee);
    }

    public function create(Admin $actor): bool
    {
        return $this->canManageEmployees($actor);
    }

    public function update(Admin $actor, Employee $employee): bool
    {
        return $this->canManageEmployee($actor, $employee);
    }

    public function delete(Admin $actor, Employee $employee): bool
    {
        return (int) $employee->admin_id !== (int) $actor->id
            && $this->canManageEmployee($actor, $employee);
    }

    private function canManageEmployees(Admin $actor): bool
    {
        if (!$this->permissions->has($actor, 'employees.manage')) {
            return false;
        }

        return $actor->role !== 'manager' || (bool) $this->managerEmployeeId($actor);
    }

    private function canManageEmployee(Admin $actor, Employee $employee): bool
    {
        if (!$this->canManageEmployees($actor)) {
            return false;
        }

        if ($actor->role !== 'manager') {
            return true;
        }

        return (int) $employee->supervisor_id === (int) $this->managerEmployeeId($actor);
    }

    private function managerEmployeeId(Admin $actor)
    {
        return $actor->employee()
            ->where('is_active', true)
            ->value('id');
    }
}
