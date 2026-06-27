<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{
    use SoftDeletes;

    public const FIELD_LABELS = [
        'chinese_name' => '中文名称',
    ];

    protected $fillable = [
        'chinese_name',
    ];

    public function skuMatches()
    {
        return $this->hasMany(SkuMatchProductType::class);
    }

    public function processingCraft()
    {
        return $this->hasOne(ProductProcessingCraft::class);
    }
}
