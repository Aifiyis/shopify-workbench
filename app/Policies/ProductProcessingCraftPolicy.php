<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ProductProcessingCraft;
use App\Services\PermissionService;

class ProductProcessingCraftPolicy
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

    public function view(Admin $actor, ProductProcessingCraft $processingCraft): bool
    {
        return $actor->isActiveAccount();
    }

    public function create(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'order_processing.manage');
    }

    public function update(Admin $actor, ProductProcessingCraft $processingCraft): bool
    {
        return $this->permissions->has($actor, 'order_processing.manage');
    }

    public function delete(Admin $actor, ProductProcessingCraft $processingCraft): bool
    {
        return $this->permissions->has($actor, 'order_processing.manage');
    }
}
