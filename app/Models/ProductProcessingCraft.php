<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductProcessingCraft extends Model
{
    use SoftDeletes;

    protected $table = 'product_processing_craft';

    public const FIELD_LABELS = [
        'chinese_name' => '中文名称',
        'craft_id' => '工艺',
        'order_processor' => '订单处理人',
        'artwork_processor' => '图画处理人',
        'procurement_processor' => '采购处理人',
        'order_processor_employee_id' => '订单处理人',
        'artwork_processor_employee_id' => '图画处理人',
        'procurement_processor_employee_id' => '采购处理人',
        'settlement_method' => '结算方式',
        'spreadsheet_template' => '表格模板',
        'spreadsheet_template_description' => '表格模板说明',
    ];

    protected $fillable = [
        'chinese_name',
        'product_type_id',
        'craft_id',
        'order_processor',
        'artwork_processor',
        'procurement_processor',
        'order_processor_employee_id',
        'artwork_processor_employee_id',
        'procurement_processor_employee_id',
        'settlement_method',
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

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    public function orderProcessorEmployee()
    {
        return $this->belongsTo(Employee::class, 'order_processor_employee_id');
    }

    public function artworkProcessorEmployee()
    {
        return $this->belongsTo(Employee::class, 'artwork_processor_employee_id');
    }

    public function procurementProcessorEmployee()
    {
        return $this->belongsTo(Employee::class, 'procurement_processor_employee_id');
    }

    public function orderProcessorEmployees()
    {
        return $this->belongsToMany(
            Employee::class,
            'product_processing_craft_employee_assignment'
        )
            ->withPivot('assignment_type')
            ->wherePivot('assignment_type', 'order_processing')
            ->withTimestamps()
            ->withTrashed();
    }

    public function artworkProcessorEmployees()
    {
        return $this->belongsToMany(
            Employee::class,
            'product_processing_craft_employee_assignment'
        )
            ->withPivot('assignment_type')
            ->wherePivot('assignment_type', 'artwork_processing')
            ->withTimestamps()
            ->withTrashed();
    }

    public function procurementProcessorEmployees()
    {
        return $this->belongsToMany(
            Employee::class,
            'product_processing_craft_employee_assignment'
        )
            ->withPivot('assignment_type')
            ->wherePivot('assignment_type', 'procurement')
            ->withTimestamps()
            ->withTrashed();
    }
}
