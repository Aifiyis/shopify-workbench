<?php

namespace App\Console\Commands;

use App\Models\ProcessedFile;
use App\Services\DataProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDataProcessingUpload extends Command
{
    protected $signature = 'data-processing:process-upload {processedFileId}';

    protected $description = 'Process an uploaded order file from a processed_files record.';

    private $dataProcessingService;

    public function __construct(DataProcessingService $dataProcessingService)
    {
        parent::__construct();
        $this->dataProcessingService = $dataProcessingService;
    }

    public function handle()
    {
        $processedFileId = (int) $this->argument('processedFileId');
        $processedFile = ProcessedFile::find($processedFileId);

        if (!$processedFile) {
            $this->error("Processed file {$processedFileId} was not found.");
            return 1;
        }

        if ($processedFile->status !== 'processing') {
            $this->warn("Processed file {$processedFileId} is {$processedFile->status}; nothing to do.");
            return 0;
        }

        $tempPath = $processedFile->file_path;

        try {
            if (!file_exists($tempPath)) {
                throw new \RuntimeException('Uploaded temp file is missing: ' . $tempPath);
            }

            Log::info('Background data processing started.', [
                'processed_file_id' => $processedFile->id,
                'original_filename' => $processedFile->original_filename,
                'temp_path' => $tempPath,
            ]);

            $result = $this->dataProcessingService->processOrderFileAll(
                $tempPath,
                $processedFile->original_filename
            );

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['error'] ?? 'Processing failed.');
            }

            $finalFilename = $this->uniqueProcessedFilename(
                $processedFile,
                $result['output_filename'],
                $result['output_path']
            );
            $finalPath = $this->finalOutputPath($finalFilename, $result['output_path']);

            $processedFile->update([
                'processed_filename' => $finalFilename,
                'file_path' => $finalPath,
                'status' => 'completed',
                'error_message' => null,
                'expires_at' => now()->addHour(),
            ]);

            @unlink($tempPath);

            Log::info('Background data processing completed.', [
                'processed_file_id' => $processedFile->id,
                'output_filename' => $processedFile->processed_filename,
                'rows_processed' => $result['rows_processed'] ?? null,
                'template_rows_processed' => $result['template_rows_processed'] ?? null,
            ]);

            $this->info('Data processing completed.');
            return 0;
        } catch (\Throwable $e) {
            $processedFile->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Background data processing failed.', [
                'processed_file_id' => $processedFile->id,
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());
            return 1;
        }
    }

    private function uniqueProcessedFilename(ProcessedFile $processedFile, $outputFilename, $outputPath)
    {
        $duplicateExists = ProcessedFile::where('processed_filename', $outputFilename)
            ->where('id', '!=', $processedFile->id)
            ->exists();

        if (!$duplicateExists) {
            return $outputFilename;
        }

        $extension = pathinfo($outputFilename, PATHINFO_EXTENSION);
        $baseName = pathinfo($outputFilename, PATHINFO_FILENAME);
        $uniqueFilename = $baseName . '_' . $processedFile->id . ($extension ? '.' . $extension : '');
        $uniquePath = dirname($outputPath) . DIRECTORY_SEPARATOR . $uniqueFilename;

        if (file_exists($outputPath)) {
            @rename($outputPath, $uniquePath);
        }

        return $uniqueFilename;
    }

    private function finalOutputPath($outputFilename, $outputPath)
    {
        $finalPath = dirname($outputPath) . DIRECTORY_SEPARATOR . $outputFilename;

        if ($finalPath !== $outputPath && file_exists($finalPath)) {
            return $finalPath;
        }

        return $outputPath;
    }
}
