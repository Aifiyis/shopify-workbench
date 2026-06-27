<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class)->withTimestamps();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'position_permission')->withTimestamps();
    }
}
