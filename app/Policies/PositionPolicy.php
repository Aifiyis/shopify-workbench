<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Position;
use App\Services\PermissionService;

class PositionPolicy
{
    private $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function viewAny(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'positions.manage');
    }

    public function view(Admin $actor, Position $position): bool
    {
        return $this->permissions->has($actor, 'positions.manage');
    }

    public function create(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'positions.manage');
    }

    public function update(Admin $actor, Position $position): bool
    {
        return $this->permissions->has($actor, 'positions.manage');
    }

    public function delete(Admin $actor, Position $position): bool
    {
        return $this->permissions->has($actor, 'positions.manage');
    }

    public function assignPermissions(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'permissions.assign');
    }
}
