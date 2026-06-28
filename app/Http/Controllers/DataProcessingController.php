<?php

namespace App\Http\Controllers;

use App\Models\ProcessedFile;
use App\Services\DataProcessingUploadDispatcher;
use App\Services\FileExpirationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataProcessingController extends Controller
{
    private $fileExpirationService;
    private $dataProcessingUploadDispatcher;

    public function __construct(
        FileExpirationService $fileExpirationService,
        DataProcessingUploadDispatcher $dataProcessingUploadDispatcher
    ) {
        $this->middleware('auth:admin');
        $this->fileExpirationService = $fileExpirationService;
        $this->dataProcessingUploadDispatcher = $dataProcessingUploadDispatcher;
    }

    public function index(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        // Get user's processed files
        $processedFiles = ProcessedFile::where('admin_id', $admin->id)
            ->orderBy('uploaded_at', 'desc')
            ->paginate(10);

        // Check for expired files and clean them up
        $this->fileExpirationService->cleanExpiredFiles();

        // Add expiry info to each file
        foreach ($processedFiles as $file) {
            $file->expiry_info = $this->fileExpirationService->getExpiryInfo($file->id);
        }

        return view('data-processing.index', [
            'processedFiles' => $processedFiles,
        ]);
    }

    public function upload(Request $request)
    {
        \Log::info('Data processing upload request received.', [
            'method' => $request->method(),
            'path' => $request->path(),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'content_type' => $request->server('CONTENT_TYPE'),
            'has_file' => $request->hasFile('file'),
            'file_error' => isset($_FILES['file']) ? ($_FILES['file']['error'] ?? null) : null,
            'file_name' => isset($_FILES['file']) ? ($_FILES['file']['name'] ?? null) : null,
        ]);

        if (!$request->hasFile('file')) {
            \Log::warning('Data processing upload reached controller without an uploaded file.', [
                'content_length' => $request->server('CONTENT_LENGTH'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'files' => $_FILES,
            ]);

            return redirect()->route('data-processing.index')
                ->with('error', '未收到上传文件。请重新选择文件后上传；如果问题反复出现，文件可能超过 PHP 上传大小限制。');
        }

        $request->validate([
            'file' => 'file',
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()
                ->withErrors(['file' => '文件类型必须为 xlsx、xls 或 csv。'])
                ->withInput();
        }

        $admin = Auth::guard('admin')->user();

        $processedFile = null;

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $safeTempName = uniqid('upload_', true) . '.' . $extension;

            // Store uploaded file temporarily, then process it outside the web request.
            $tempPath = $file->storeAs('temp', $safeTempName, 'local');
            $fullTempPath = storage_path('app/' . $tempPath);

            $processedFile = ProcessedFile::create([
                'admin_id' => $admin->id,
                'original_filename' => $originalName,
                'processed_filename' => 'processing_' . pathinfo($safeTempName, PATHINFO_FILENAME) . '.zip',
                'file_path' => $fullTempPath,
                'status' => 'processing',
                'uploaded_at' => now(),
                'expires_at' => now()->addHour(),
            ]);

            $this->dataProcessingUploadDispatcher->dispatch($processedFile->id);

            return redirect()->route('data-processing.index')
                ->with('success', '文件上传成功，已开始处理。请稍后刷新页面，下载订单 Excel 导出压缩包。');
        } catch (\Exception $e) {
            if ($processedFile) {
                $processedFile->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            \Log::error('File upload processing failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', '处理文件时发生错误：' . $e->getMessage());
        }
    }

    public function download($id)
    {
        $admin = Auth::guard('admin')->user();
        $processedFile = ProcessedFile::where('id', $id)
            ->where('admin_id', $admin->id)
            ->firstOrFail();

        if ($processedFile->status !== 'completed') {
            abort(409, '文件尚未处理完成，暂时无法下载');
        }

        // Check if file is expired
        if ($processedFile->isExpired()) {
            abort(410, '文件已过期，无法继续下载');
        }

        // Check if file exists
        if (!file_exists($processedFile->file_path)) {
            abort(404, '未找到文件');
        }

        // Mark as downloaded
        $this->fileExpirationService->markAsDownloaded($id);

        return response()->download($processedFile->file_path, $processedFile->processed_filename);
    }

    public function delete($id)
    {
        $admin = Auth::guard('admin')->user();
        $processedFile = ProcessedFile::where('id', $id)
            ->where('admin_id', $admin->id)
            ->firstOrFail();

        // Delete file
        if (file_exists($processedFile->file_path)) {
            unlink($processedFile->file_path);
        }

        // Delete record
        $processedFile->delete();

        return redirect()->route('data-processing.index')
            ->with('success', '文件删除成功');
    }
}
