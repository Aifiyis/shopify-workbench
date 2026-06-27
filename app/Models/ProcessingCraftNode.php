<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessingCraftNode extends Model
{
    public const FIELD_LABELS = [
        'parent_id' => '上级工艺',
        'name' => '工艺节点名称',
        'path' => '完整工艺路径',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'path',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
