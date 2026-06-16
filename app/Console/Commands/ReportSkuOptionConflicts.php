<?php

namespace App\Console\Commands;

use App\Services\SkuOptionScrapeService;
use Illuminate\Console\Command;

class ReportSkuOptionConflicts extends Command
{
    protected $signature = 'sku-options:conflicts
        {--cleaned-sku=* : Limit conflict checks to one or more cleaned_sku values}
        {--rerun : Rerun conflicting product URLs and compare the fresh result}
        {--timeout=120 : Browser timeout per URL in seconds}
        {--output= : Markdown report path}';
    protected $description = 'Report option image conflicts for cleaned_sku groups and optionally rerun those product URLs.';

    private $scrapeService;

    public function __construct(SkuOptionScrapeService $scrapeService)
    {
        parent::__construct();

        $this->scrapeService = $scrapeService;
    }

    public function handle()
    {
        try {
            $result = $this->scrapeService->conflictReport(
                (array) $this->option('cleaned-sku'),
                (bool) $this->option('rerun'),
                (int) $this->option('timeout'),
                $this->option('output') ?: null
            );

            $this->line('Current conflict count: ' . $result['current_conflict_count']);

            if ($this->option('rerun')) {
                $this->line('Rerun conflict count: ' . $result['rerun_conflict_count']);
            }

            $this->line('Report: ' . $result['report_path']);
            $this->line('');
            $this->line($result['markdown']);

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
