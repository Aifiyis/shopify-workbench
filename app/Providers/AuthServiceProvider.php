<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Position;
use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use App\Policies\AdminPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\PositionPolicy;
use App\Policies\ProcessingCraftNodePolicy;
use App\Policies\ProductProcessingCraftPolicy;
use App\Policies\ProductTypePolicy;
use App\Policies\SkuMatchProductTypePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        SkuMatchProductType::class => SkuMatchProductTypePolicy::class,
        ProductType::class => ProductTypePolicy::class,
        ProductProcessingCraft::class => ProductProcessingCraftPolicy::class,
        ProcessingCraftNode::class => ProcessingCraftNodePolicy::class,
        Employee::class => EmployeePolicy::class,
        Position::class => PositionPolicy::class,
        Admin::class => AdminPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user) {
            if ($user instanceof Admin && $user->role === 'super' && $user->isActiveAccount()) {
                return true;
            }

            return null;
        });
    }
}
