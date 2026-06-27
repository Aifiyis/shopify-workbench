<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkuMatchProductType extends Model
{
    use SoftDeletes;

    protected $table = 'sku_match_product_type';

    public const FIELD_LABELS = [
        'original_sku' => '原始SKU',
        'cleaned_sku' => '清洗后的SKU',
        'chinese_name' => '中文名称',
        'product_lister' => '上品人',
        'product_lister_employee_id' => '上品人',
    ];

    protected $fillable = [
        'original_sku',
        'cleaned_sku',
        'product_type_id',
        'chinese_name',
        'product_lister',
        'product_lister_employee_id',
    ];

    public function processingCraft()
    {
        return $this->belongsTo(ProductProcessingCraft::class, 'chinese_name', 'chinese_name');
    }

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    public function productListerEmployee()
    {
        return $this->belongsTo(Employee::class, 'product_lister_employee_id');
    }
}
