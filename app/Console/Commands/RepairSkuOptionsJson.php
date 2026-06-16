<?php

namespace App\Console\Commands;

use App\Services\SkuOptionScrapeService;
use Illuminate\Console\Command;

class RepairSkuOptionsJson extends Command
{
    protected $signature = 'sku-options:repair-json
        {--timeout=120 : Browser timeout per URL in seconds}
        {--id=* : Product id to rerun and replace in the existing JSON}
        {--retry-connection-closed : Rerun products whose error contains ERR_CONNECTION_CLOSED}
        {--retry-unknown-sku : Rerun unknown plugin products with non-empty SKU and No supported option plugin error}
        {--retry-unknown-failed : Rerun products whose plugin is unknown and status is failed}
        {--batch-size=10 : Number of products to repair before writing JSON output}
        {--normalize-sort-order : Rewrite option sort_order from 1 within each product_id}';
    protected $description = 'Repair existing SKU option JSON by normalizing sort order and rerunning selected products.';

    private $scrapeService;

    public function __construct(SkuOptionScrapeService $scrapeService)
    {
        parent::__construct();

        $this->scrapeService = $scrapeService;
    }

    public function handle()
    {
        $didWork = false;

        if ($this->option('normalize-sort-order')) {
            $result = $this->scrapeService->normalizeOutputSortOrdersFile();
            $this->line('Normalized sort_order in: ' . $result['output_path']);
            $this->line('Products: ' . $result['products_count'] . ', Options: ' . $result['options_count']);
            $didWork = true;
        }

        $ids = array_map('intval', (array) $this->option('id'));
        $candidateIds = $this->scrapeService->outputRepairCandidateIds(
            (bool) $this->option('retry-connection-closed'),
            (bool) $this->option('retry-unknown-sku'),
            (bool) $this->option('retry-unknown-failed')
        );
        $ids = array_values(array_unique(array_filter(array_merge($ids, $candidateIds), function ($id) {
            return $id > 0;
        })));

        if (!empty($ids)) {
            $batchSize = max(1, (int) $this->option('batch-size'));
            $this->line('Repairing ' . count($ids) . ' product id(s) in batches of ' . $batchSize . ': ' . implode(', ', $ids));

            $repairedIds = [];
            $failedBatches = [];
            $lastResult = null;

            foreach (array_chunk($ids, $batchSize) as $batchIndex => $batchIds) {
                $this->line('Repair batch ' . ($batchIndex + 1) . ' ids: ' . implode(', ', $batchIds));

                try {
                    $lastResult = $this->scrapeService->repairOutputProductIds($batchIds, (int) $this->option('timeout'));
                    $repairedIds = array_merge($repairedIds, $lastResult['repaired_ids']);
                    $this->line('Repaired batch ids: ' . implode(', ', $lastResult['repaired_ids']));
                    $this->line('Products: ' . $lastResult['products_count'] . ', Options: ' . $lastResult['options_count']);
                } catch (\Exception $e) {
                    $failedBatches[] = $batchIds;
                    $this->error('Repair batch failed for ids ' . implode(', ', $batchIds) . ': ' . $e->getMessage());
                }
            }

            $this->line('Repaired ids: ' . implode(', ', array_values(array_unique($repairedIds))));

            if ($lastResult) {
                $this->line('JSON output: ' . $lastResult['output_path']);
                $this->line('Products: ' . $lastResult['products_count'] . ', Options: ' . $lastResult['options_count']);
            }

            if (!empty($failedBatches)) {
                return 1;
            }

            $didWork = true;
        }

        if (!$didWork) {
            $this->error('Nothing to repair. Use --normalize-sort-order, --id, --retry-connection-closed, or --retry-unknown-sku.');
            return 1;
        }

        return 0;
    }
}
