<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ProductType;
use App\Services\PermissionService;

class ProductTypePolicy
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

    public function view(Admin $actor, ProductType $productType): bool
    {
        return $actor->isActiveAccount();
    }

    public function create(Admin $actor): bool
    {
        return $this->permissions->has($actor, 'sku_product_types.manage');
    }

    public function update(Admin $actor, ProductType $productType): bool
    {
        return $this->permissions->has($actor, 'sku_product_types.manage');
    }

    public function delete(Admin $actor, ProductType $productType): bool
    {
        return $this->permissions->has($actor, 'sku_product_types.manage');
    }
}
