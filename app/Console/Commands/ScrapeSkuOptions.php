<?php

namespace App\Console\Commands;

use App\Services\SkuOptionScrapeService;
use Illuminate\Console\Command;

class ScrapeSkuOptions extends Command
{
    protected $signature = 'sku-options:scrape
        {input : Text file with one product URL per line}
        {--timeout=120 : Browser timeout per URL in seconds}
        {--batch-size=0 : Process URLs in batches and write JSON/progress after each batch}
        {--start=1 : 1-based URL index to start from when batching}
        {--progress= : Progress JSON path for batch mode}
        {--append : Append results to the existing output JSON instead of replacing it when start is 1}';
    protected $description = 'Scrape dynamic SKU option names and swatch images from product pages.';

    private $scrapeService;

    public function __construct(SkuOptionScrapeService $scrapeService)
    {
        parent::__construct();

        $this->scrapeService = $scrapeService;
    }

    public function handle()
    {
        $inputPath = $this->argument('input');

        if (!file_exists($inputPath)) {
            $storagePath = storage_path('app/' . ltrim($inputPath, '/\\'));

            if (file_exists($storagePath)) {
                $inputPath = $storagePath;
            }
        }

        if (!file_exists($inputPath)) {
            $this->error('Input URL file not found: ' . $this->argument('input'));
            return 1;
        }

        $urls = array_values(array_filter(array_map('trim', file($inputPath)), function ($line) {
            return $line !== '';
        }));

        if (empty($urls)) {
            $this->error('Input URL file is empty.');
            return 1;
        }

        try {
            $this->info('Scraping SKU option images from ' . count($urls) . ' URL(s)...');

            $batchSize = (int) $this->option('batch-size');

            if ($batchSize > 0) {
                $progressPath = $this->option('progress') ?: storage_path('app/private/sku-options-progress.json');
                $result = $this->scrapeService->scrapeInBatches(
                    $urls,
                    (int) $this->option('timeout'),
                    $batchSize,
                    (int) $this->option('start'),
                    $progressPath,
                    function (array $progress) {
                        if (($progress['status'] ?? '') === 'completed') {
                            $this->line('Completed all URLs. Products: ' . ($progress['products_count'] ?? 0) . ', Options: ' . ($progress['options_count'] ?? 0));
                            return;
                        }

                        $this->line(
                            'Completed URLs ' . ($progress['last_batch_start'] ?? '?') . '-' . ($progress['last_batch_end'] ?? '?')
                            . ' / ' . ($progress['total_urls'] ?? '?')
                            . '; next start: ' . ($progress['next_start_index'] ?? '?')
                            . '; products: ' . ($progress['products_count'] ?? 0)
                            . '; options: ' . ($progress['options_count'] ?? 0)
                        );
                    },
                    (bool) $this->option('append')
                );
            } else {
                $result = $this->scrapeService->scrape($urls, (int) $this->option('timeout'));
            }

            $this->line('JSON output: ' . $result['output_path']);
            $this->line('Image directory: ' . $result['image_directory']);
            if (isset($result['progress_path'])) {
                $this->line('Progress: ' . $result['progress_path']);
            }
            $this->line('Products: ' . $result['products_count']);
            $this->line('Options: ' . $result['options_count']);
            $this->info('SKU option scraping complete.');

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
