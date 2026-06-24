<?php

namespace App\Services;

use RuntimeException;

class DataProcessingUploadDispatcher
{
    public function dispatch($processedFileId)
    {
        $logPath = storage_path('logs/data-processing-upload-' . (int) $processedFileId . '.log');
        $phpBinary = $this->phpBinary();
        $artisan = base_path('artisan');

        $command = $this->backgroundCommand($phpBinary, $artisan, (int) $processedFileId, $logPath);
        $handle = popen($command, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to start background data processing command.');
        }

        pclose($handle);
    }

    private function backgroundCommand($phpBinary, $artisan, $processedFileId, $logPath)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $innerCommand = 'start /B "" '
                . escapeshellarg($phpBinary) . ' '
                . '-d memory_limit=1024M '
                . escapeshellarg($artisan) . ' '
                . 'data-processing:process-upload '
                . (int) $processedFileId
                . ' >> ' . escapeshellarg($logPath) . ' 2>&1';

            return 'cmd /C ' . $innerCommand;
        }

        return escapeshellarg($phpBinary) . ' '
            . '-d memory_limit=1024M '
            . escapeshellarg($artisan) . ' '
            . 'data-processing:process-upload '
            . (int) $processedFileId
            . ' >> ' . escapeshellarg($logPath) . ' 2>&1 &';
    }

    private function phpBinary()
    {
        $binary = PHP_BINARY;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cliBinary = dirname($binary) . DIRECTORY_SEPARATOR . 'php.exe';
            if (file_exists($cliBinary)) {
                return $cliBinary;
            }
        }

        return $binary;
    }
}
