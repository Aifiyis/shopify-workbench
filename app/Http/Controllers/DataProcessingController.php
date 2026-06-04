<?php

namespace App\Http\Controllers;

use App\Models\ProcessedFile;
use App\Services\DataProcessingService;
use App\Services\FileExpirationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataProcessingController extends Controller
{
    private $dataProcessingService;
    private $fileExpirationService;

    public function __construct(
        DataProcessingService $dataProcessingService,
        FileExpirationService $fileExpirationService
    ) {
        $this->middleware('auth:admin');
        $this->dataProcessingService = $dataProcessingService;
        $this->fileExpirationService = $fileExpirationService;
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
        $request->validate([
            'file' => 'required|file',
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()
                ->withErrors(['file' => 'The file must be a file of type: xlsx, xls, csv.'])
                ->withInput();
        }

        $admin = Auth::guard('admin')->user();

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $safeTempName = uniqid('upload_', true) . '.' . $extension;

            // Store uploaded file temporarily
            $tempPath = $file->storeAs('temp', $safeTempName, 'local');
            $fullTempPath = storage_path('app/' . $tempPath);

            // Process the file
            $result = $this->dataProcessingService->processOrderFileAll($fullTempPath, $originalName);

            if (!$result['success']) {
                @unlink($fullTempPath);
                return redirect()->back()
                    ->with('error', 'Processing failed: ' . $result['error']);
            }

            // Record processed file in database
            $processedFile = ProcessedFile::create([
                'admin_id' => $admin->id,
                'original_filename' => $originalName,
                'processed_filename' => $result['output_filename'],
                'file_path' => $result['output_path'],
                'status' => 'completed',
                'uploaded_at' => now(),
                'expires_at' => now()->addHour(),
            ]);

            // Clean up temp file
            @unlink($fullTempPath);

            return redirect()->route('data-processing.index')
                ->with('success', "File processed successfully! {$result['rows_processed']} rows processed, {$result['ctcx_rows_processed']} CTCX rows exported.");
        } catch (\Exception $e) {
            \Log::error('File upload processing failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'An error occurred while processing the file: ' . $e->getMessage());
        }
    }

    public function download($id)
    {
        $admin = Auth::guard('admin')->user();
        $processedFile = ProcessedFile::where('id', $id)
            ->where('admin_id', $admin->id)
            ->firstOrFail();

        // Check if file is expired
        if ($processedFile->isExpired()) {
            abort(410, 'File has expired and is no longer available');
        }

        // Check if file exists
        if (!file_exists($processedFile->file_path)) {
            abort(404, 'File not found');
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
            ->with('success', 'File deleted successfully');
    }
}
