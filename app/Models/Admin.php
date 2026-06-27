<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'parent_admin_id',
        'company_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(Admin::class, 'parent_admin_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Admin::class, 'parent_admin_id');
    }

    public function storeAccess()
    {
        return $this->hasMany(AdminStoreAccess::class, 'admin_id');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'admin_id');
    }

    public function stores()
    {
        return $this->belongsToMany(
            ShopifyStore::class,
            'admin_store_access',
            'admin_id',
            'store_id'
        )->withPivot('access_level');
    }

    public function canAccessStore($storeId)
    {
        return $this->role === 'super' ||
               $this->stores()->where('store_id', $storeId)->exists();
    }

    public function canManage($adminId)
    {
        // Super admin can manage anyone
        if ($this->role === 'super') {
            return true;
        }

        // Manager can manage direct subordinates only
        if ($this->role === 'manager') {
            return $this->subordinates()->where('id', $adminId)->exists();
        }

        return false;
    }

    public function getSubordinateTree()
    {
        return $this->subordinates()->with('subordinates')->get();
    }
}
