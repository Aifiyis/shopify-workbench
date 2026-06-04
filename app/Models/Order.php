<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'shopify_order_id',
        'order_date',
        'order_name',
        'customer_name',
        'total_price',
        'currency',
        'status',
        'line_items_count',
        'cached_at',
        'expires_at',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_price' => 'decimal:2',
        'cached_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(ShopifyStore::class, 'store_id');
    }

    public function lineItems()
    {
        return $this->hasMany(OrderLineItem::class, 'order_id');
    }
}
