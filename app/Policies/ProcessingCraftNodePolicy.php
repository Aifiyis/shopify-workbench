<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ProcessingCraftNode;
use App\Services\PermissionService;

class ProcessingCraftNodePolicy
{
    private $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function viewAny(Admin $actor): bool
    {
        return $actor->isActiveAccount();
    }

    public function view(Admin $actor, ProcessingCraftNode $craftNode): bool
    {
        return $actor->isActiveAccount();
    }

    public function create(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'processing_crafts.manage');
    }

    public function update(Admin $actor, ProcessingCraftNode $craftNode): bool
    {
        return $this->permissions->has($actor, 'processing_crafts.manage');
    }

    public function delete(Admin $actor, ProcessingCraftNode $craftNode): bool
    {
        return $this->permissions->has($actor, 'processing_crafts.manage');
    }
}
