<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminStoreAccess extends Model
{
    use HasFactory;

    protected $table = 'admin_store_access';

    protected $fillable = [
        'admin_id',
        'store_id',
        'access_level',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function store()
    {
        return $this->belongsTo(ShopifyStore::class);
    }
}
