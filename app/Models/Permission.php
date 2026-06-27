<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_delegable',
    ];

    protected $casts = [
        'is_delegable' => 'boolean',
    ];

    public function positions()
    {
        return $this->belongsToMany(Position::class, 'position_permission')->withTimestamps();
    }
}
