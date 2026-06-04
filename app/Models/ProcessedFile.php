<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'original_filename',
        'processed_filename',
        'file_path',
        'status',
        'error_message',
        'uploaded_at',
        'expires_at',
        'is_downloaded',
        'downloaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'is_downloaded' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function isExpired()
    {
        return now()->isAfter($this->expires_at);
    }

    public function getExpiresInMinutes()
    {
        $diff = $this->expires_at->diffInMinutes(now());
        return max(0, $diff);
    }
}
