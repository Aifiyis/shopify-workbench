<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingCraftNode extends Model
{
    use SoftDeletes;

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
