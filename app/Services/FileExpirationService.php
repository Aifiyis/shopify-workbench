<?php

namespace App\Services;

use App\Models\ProcessedFile;
use Illuminate\Support\Facades\Storage;

class FileExpirationService
{
    private $expirationHours = 1;

    public function __construct($hours = 1)
    {
        $this->expirationHours = $hours;
    }

    public function markForExpiration($filePath, $adminId)
    {
        $filename = basename($filePath);

        ProcessedFile::create([
            'admin_id' => $adminId,
            'original_filename' => $filename,
            'processed_filename' => $filename,
            'file_path' => $filePath,
            'status' => 'completed',
            'uploaded_at' => now(),
            'expires_at' => now()->addHours($this->expirationHours),
        ]);
    }

    public function cleanExpiredFiles()
    {
        $expiredFiles = ProcessedFile::where('expires_at', '<=', now())
            ->where('is_downloaded', false)
            ->get();

        $deletedCount = 0;

        foreach ($expiredFiles as $file) {
            if (file_exists($file->file_path)) {
                try {
                    unlink($file->file_path);
                    $file->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to delete file: ' . $file->file_path . ' - ' . $e->getMessage());
                }
            } else {
                // File already deleted, just remove record
                $file->delete();
            }
        }

        return $deletedCount;
    }

    public function isFileExpired($processedFileId)
    {
        $file = ProcessedFile::find($processedFileId);

        if (!$file) {
            return true;
        }

        return $file->isExpired();
    }

    public function getExpiryInfo($processedFileId)
    {
        $file = ProcessedFile::find($processedFileId);

        if (!$file) {
            return null;
        }

        return [
            'expires_at' => $file->expires_at,
            'expires_in_minutes' => $file->getExpiresInMinutes(),
            'is_expired' => $file->isExpired(),
        ];
    }

    public function markAsDownloaded($processedFileId)
    {
        $file = ProcessedFile::find($processedFileId);

        if ($file) {
            $file->update([
                'is_downloaded' => true,
                'downloaded_at' => now(),
            ]);
        }
    }
}
