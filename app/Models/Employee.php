<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'supervisor_id',
        'admin_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class)->withTimestamps();
    }

    public function processingCraftAssignments()
    {
        return $this->belongsToMany(
            ProductProcessingCraft::class,
            'product_processing_craft_employee_assignment',
            'employee_id',
            'product_processing_craft_id'
        )
            ->withPivot('assignment_type')
            ->withTimestamps()
            ->withTrashed();
    }
}
