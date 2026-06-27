<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductProcessingCraft extends Model
{
    protected $table = 'product_processing_craft';

    public const FIELD_LABELS = [
        'chinese_name' => '中文名称',
        'craft_id' => '工艺',
        'order_processor' => '订单处理人',
        'artwork_processor' => '图画处理人',
        'procurement_processor' => '采购处理人',
        'spreadsheet_template' => '表格模板',
        'spreadsheet_template_description' => '表格模板说明',
    ];

    protected $fillable = [
        'chinese_name',
        'craft_id',
        'order_processor',
        'artwork_processor',
        'procurement_processor',
        'spreadsheet_template',
        'spreadsheet_template_description',
    ];

    public function craft()
    {
        return $this->belongsTo(ProcessingCraftNode::class, 'craft_id');
    }

    public function skuMatches()
    {
        return $this->hasMany(SkuMatchProductType::class, 'chinese_name', 'chinese_name');
    }
}
