<?php

namespace App\Console\Commands;

use App\Services\FileExpirationService;
use Illuminate\Console\Command;

class CleanExpiredProcessedFiles extends Command
{
    protected $signature = 'processed-files:clean {--hours=1 : Number of hours to retain files}';
    protected $description = 'Delete processed files that have expired';

    public function handle()
    {
        $hours = $this->option('hours');
        $expirationService = new FileExpirationService($hours);

        $deleted = $expirationService->cleanExpiredFiles();

        $this->info("Deleted {$deleted} expired processed files.");
    }
}
