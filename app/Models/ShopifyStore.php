<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyStore extends Model
{
    use HasFactory;

    protected $table = 'shopify_stores';

    protected $fillable = [
        'shop_name',
        'shop_url',
        'access_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'store_id');
    }

    public function adminAccess()
    {
        return $this->hasMany(AdminStoreAccess::class, 'store_id');
    }

    public function admins()
    {
        return $this->belongsToMany(
            Admin::class,
            'admin_store_access',
            'store_id',
            'admin_id'
        )->withPivot('access_level');
    }
}
