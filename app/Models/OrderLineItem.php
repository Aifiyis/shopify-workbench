<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderLineItem extends Model
{
    use HasFactory;

    protected $table = 'order_line_items';

    protected $fillable = [
        'order_id',
        'shopify_line_item_id',
        'product_title',
        'product_type',
        'quantity',
        'option1',
        'option3',
        'product_tags',
        'sku',
        'multi_types',
        'picture_url',
        'pic_name',
        'extra_details',
        'custom_text',
        'raw_properties',
    ];

    protected $casts = [
        'raw_properties' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
