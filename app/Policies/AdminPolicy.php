<?php

namespace App\Policies;

use App\Models\Admin;
use App\Services\PermissionService;

class AdminPolicy
{
    private $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function viewAny(Admin $actor): bool
    {
        return $this->permissions->canManageAccount($actor);
    }

    public function view(Admin $actor, Admin $target): bool
    {
        return $this->permissions->canManageAccount($actor, $target);
    }

    public function create(Admin $actor, string $targetRole = 'employee'): bool
    {
        return $this->permissions->canManageAccount($actor, null, $targetRole);
    }

    public function update(Admin $actor, Admin $target): bool
    {
        return $this->permissions->canManageAccount($actor, $target);
    }

    public function delete(Admin $actor, Admin $target): bool
    {
        return $this->permissions->canManageAccount($actor, $target);
    }
}
