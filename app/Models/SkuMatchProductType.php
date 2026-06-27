<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuMatchProductType extends Model
{
    protected $table = 'sku_match_product_type';

    public const FIELD_LABELS = [
        'original_sku' => '原始SKU',
        'cleaned_sku' => '清洗后的SKU',
        'chinese_name' => '中文名称',
        'product_lister' => '上品人',
    ];

    protected $fillable = [
        'original_sku',
        'cleaned_sku',
        'chinese_name',
        'product_lister',
    ];

    public function processingCraft()
    {
        return $this->belongsTo(ProductProcessingCraft::class, 'chinese_name', 'chinese_name');
    }
}
