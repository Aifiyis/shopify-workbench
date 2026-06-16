<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SkuOptionScrapeService
{
    private $skuCleaningService;
    private $imageDirectory;
    private $outputJsonPath;
    private $workerPath;

    public function __construct(
        SkuCleaningService $skuCleaningService,
        $imageDirectory = null,
        $outputJsonPath = null,
        $workerPath = null
    ) {
        $this->skuCleaningService = $skuCleaningService;
        $this->imageDirectory = $imageDirectory ?: storage_path('app/private/sku-options-image');
        $this->outputJsonPath = $outputJsonPath ?: storage_path('app/private/sku-options-image.json');
        $this->workerPath = $workerPath ?: base_path('scripts/sku-options-scraper.js');
    }

    public function scrape(array $urls, $timeoutSeconds = 120)
    {
        $urls = array_values(array_filter(array_map('trim', $urls), function ($url) {
            return $url !== '';
        }));

        if (empty($urls)) {
            throw new \Exception('No product URLs were provided.');
        }

        $workerData = $this->runWorker($urls, $timeoutSeconds);
        $output = $this->buildOutput($workerData['results'], true);
        $this->logScrapeErrors($output);
        $this->writeOutput($output);

        return [
            'output_path' => $this->outputJsonPath,
            'image_directory' => $this->imageDirectory,
            'products_count' => count($output['products']),
            'options_count' => count($output['options']),
            'output' => $output,
        ];
    }

    public function scrapeInBatches(array $urls, $timeoutSeconds = 120, $batchSize = 10, $startIndex = 1, $progressPath = null, callable $onProgress = null, $appendOutput = false)
    {
        $urls = array_values(array_filter(array_map('trim', $urls), function ($url) {
            return $url !== '';
        }));

        if (empty($urls)) {
            throw new \Exception('No product URLs were provided.');
        }

        $total = count($urls);
        $batchSize = max(1, (int) $batchSize);
        $startIndex = max(1, (int) $startIndex);
        $progressPath = $progressPath ?: storage_path('app/private/sku-options-progress.json');
        $output = $appendOutput ? $this->loadOutput() : $this->loadOutputForResume($startIndex);

        for ($offset = $startIndex - 1; $offset < $total; $offset += $batchSize) {
            $batchUrls = array_slice($urls, $offset, $batchSize);
            $batchStart = $offset + 1;
            $batchEnd = $offset + count($batchUrls);

            $this->writeProgress($progressPath, [
                'status' => 'running',
                'total_urls' => $total,
                'batch_size' => $batchSize,
                'current_batch_start' => $batchStart,
                'current_batch_end' => $batchEnd,
                'completed_urls' => $offset,
                'next_start_index' => $batchStart,
                'updated_at' => date('c'),
            ]);

            try {
                $workerData = $this->runWorker($batchUrls, $timeoutSeconds);
                $batchOutput = $this->buildOutput(
                    $workerData['results'],
                    true,
                    $this->nextProductId($output),
                    $this->nextSortOrder($output)
                );
                $this->logScrapeErrors($batchOutput);
                $output = $this->mergeOutput($output, $batchOutput);
                $this->writeOutput($output);

                $progress = [
                    'status' => 'running',
                    'total_urls' => $total,
                    'batch_size' => $batchSize,
                    'last_batch_start' => $batchStart,
                    'last_batch_end' => $batchEnd,
                    'completed_urls' => $batchEnd,
                    'next_start_index' => $batchEnd + 1,
                    'last_url' => end($batchUrls),
                    'products_count' => count($output['products']),
                    'options_count' => count($output['options']),
                    'output_path' => $this->outputJsonPath,
                    'image_directory' => $this->imageDirectory,
                    'updated_at' => date('c'),
                ];
                $this->writeProgress($progressPath, $progress);

                if ($onProgress) {
                    $onProgress($progress);
                }
            } catch (\Exception $e) {
                $this->writeProgress($progressPath, [
                    'status' => 'failed',
                    'total_urls' => $total,
                    'batch_size' => $batchSize,
                    'current_batch_start' => $batchStart,
                    'current_batch_end' => $batchEnd,
                    'completed_urls' => max(0, $batchStart - 1),
                    'next_start_index' => $batchStart,
                    'error' => $e->getMessage(),
                    'updated_at' => date('c'),
                ]);

                throw $e;
            }
        }

        $progress = [
            'status' => 'completed',
            'total_urls' => $total,
            'batch_size' => $batchSize,
            'completed_urls' => $total,
            'next_start_index' => $total + 1,
            'products_count' => count($output['products']),
            'options_count' => count($output['options']),
            'output_path' => $this->outputJsonPath,
            'image_directory' => $this->imageDirectory,
            'updated_at' => date('c'),
        ];
        $this->writeProgress($progressPath, $progress);

        if ($onProgress) {
            $onProgress($progress);
        }

        return [
            'output_path' => $this->outputJsonPath,
            'image_directory' => $this->imageDirectory,
            'products_count' => count($output['products']),
            'options_count' => count($output['options']),
            'progress_path' => $progressPath,
            'output' => $output,
        ];
    }

    private function runWorker(array $urls, $timeoutSeconds)
    {
        if (!file_exists($this->workerPath)) {
            throw new \Exception("SKU option scraper worker not found: {$this->workerPath}");
        }

        $tempDirectory = storage_path('app/temp/sku-options');
        $this->ensureDirectory($tempDirectory);
        $inputPath = $tempDirectory . DIRECTORY_SEPARATOR . uniqid('input_', true) . '.json';
        $workerOutputPath = $tempDirectory . DIRECTORY_SEPARATOR . uniqid('worker_output_', true) . '.json';

        file_put_contents($inputPath, json_encode(['urls' => $urls], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $process = new Process([
            'node',
            $this->workerPath,
            '--input',
            $inputPath,
            '--output',
            $workerOutputPath,
            '--timeout',
            (string) $timeoutSeconds,
        ], base_path(), $this->nodeProcessEnvironment(), null, $timeoutSeconds * max(2, count($urls)) + 30);

        $process->run();

        @unlink($inputPath);

        if (!$process->isSuccessful()) {
            @unlink($workerOutputPath);
            throw new \Exception('SKU option scraper worker failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        if (!file_exists($workerOutputPath)) {
            throw new \Exception('SKU option scraper worker did not write an output file.');
        }

        $workerData = json_decode(file_get_contents($workerOutputPath), true);
        @unlink($workerOutputPath);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($workerData['results']) || !is_array($workerData['results'])) {
            throw new \Exception('Invalid SKU option scraper worker output: ' . json_last_error_msg());
        }

        return $workerData;
    }

    public function appendWorkerResults(array $output, array $workerResults, $downloadImages = false)
    {
        return $this->mergeOutput($output, $this->buildOutput(
            $workerResults,
            $downloadImages,
            $this->nextProductId($output),
            $this->nextSortOrder($output)
        ));
    }

    public function repairOutputProductIds(array $productIds, $timeoutSeconds = 120)
    {
        $output = $this->loadOutput();
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $targets = [];

        foreach ($output['products'] ?? [] as $product) {
            $id = (int) ($product['id'] ?? 0);

            if ($id > 0 && in_array($id, $productIds, true) && !empty($product['product_url'])) {
                $targets[] = $product;
            }
        }

        if (empty($targets)) {
            return [
                'output_path' => $this->outputJsonPath,
                'products_count' => count($output['products'] ?? []),
                'options_count' => count($output['options'] ?? []),
                'repaired_count' => 0,
                'requested_ids' => $productIds,
                'repaired_ids' => [],
            ];
        }

        $urls = array_map(function ($product) {
            return $product['product_url'];
        }, $targets);
        $workerData = $this->runWorker($urls, $timeoutSeconds);
        $replacements = [];
        $logOutput = [
            'products' => [],
            'options' => [],
        ];

        foreach ($workerData['results'] as $index => $result) {
            $target = $targets[$index];
            $targetId = (int) ($target['id'] ?? 0);
            $built = $this->buildOutput([$result], true, $targetId);
            $product = $built['products'][0] ?? null;

            if ($product) {
                $product = $this->mergeProductMetadata($product, $target);
            }

            $replacements[$targetId] = [
                'product' => $product,
                'options' => $built['options'] ?? [],
            ];

            if ($product) {
                $logOutput['products'][] = $product;
            }

            foreach ($built['options'] ?? [] as $option) {
                $logOutput['options'][] = $option;
            }
        }

        $output = $this->replaceOutputProducts($output, $replacements);
        $output = $this->normalizeOutputSortOrders($output);
        $this->writeOutput($output);
        $this->logScrapeErrors($logOutput);

        return [
            'output_path' => $this->outputJsonPath,
            'products_count' => count($output['products'] ?? []),
            'options_count' => count($output['options'] ?? []),
            'repaired_count' => count($replacements),
            'requested_ids' => $productIds,
            'repaired_ids' => array_keys($replacements),
        ];
    }

    public function normalizeOutputSortOrdersFile()
    {
        $output = $this->normalizeOutputSortOrders($this->loadOutput());
        $this->writeOutput($output);

        return [
            'output_path' => $this->outputJsonPath,
            'products_count' => count($output['products'] ?? []),
            'options_count' => count($output['options'] ?? []),
        ];
    }

    public function conflictReport(array $targetCleanedSkus = [], $rerun = false, $timeoutSeconds = 120, $reportPath = null)
    {
        $output = $this->loadOutput();
        $targetCleanedSkus = array_values(array_unique(array_filter(array_map('trim', $targetCleanedSkus), function ($sku) {
            return $sku !== '';
        })));
        $targetMap = array_fill_keys($targetCleanedSkus, true);
        $currentConflicts = $this->findOptionImageConflicts($output, $targetMap);
        $rerunOutput = null;
        $rerunConflicts = [];
        $rerunGroups = [];

        if ($rerun && !empty($currentConflicts)) {
            $targets = $this->productsFromConflicts($currentConflicts);
            $urls = array_map(function ($product) {
                return $product['product_url'];
            }, $targets);
            $workerData = $this->runWorker($urls, $timeoutSeconds);
            $rerunOutput = $this->buildRerunOutput($workerData['results'], $targets);
            $retryTargets = $this->zeroOptionRetryTargets($targets, $rerunOutput);

            if (!empty($retryTargets)) {
                $retryUrls = array_map(function ($product) {
                    return $product['product_url'];
                }, $retryTargets);
                $retryWorkerData = $this->runWorker($retryUrls, $timeoutSeconds);
                $retryOutput = $this->buildRerunOutput($retryWorkerData['results'], $retryTargets);
                $rerunOutput = $this->replaceRerunOutputProducts($rerunOutput, $retryOutput);
            }

            $rerunGroups = $this->groupOptionImageProducts($rerunOutput, $targetMap);
            $rerunConflicts = $this->findOptionImageConflicts($rerunOutput, $targetMap);
        }

        $reportPath = $reportPath ?: storage_path('app/private/sku-options-conflict-report.md');
        $this->ensureDirectory(dirname($reportPath));
        $markdown = $this->formatConflictReportMarkdown($currentConflicts, $rerunConflicts, $rerunGroups, $rerun);
        file_put_contents($reportPath, $markdown);

        return [
            'report_path' => $reportPath,
            'current_conflict_count' => count($currentConflicts),
            'rerun_conflict_count' => $rerun ? count($rerunConflicts) : null,
            'current_conflicts' => $currentConflicts,
            'rerun_conflicts' => $rerunConflicts,
            'rerun_groups' => $rerunGroups,
            'markdown' => $markdown,
        ];
    }

    private function buildRerunOutput(array $workerResults, array $targets)
    {
        $output = [
            'products' => [],
            'options' => [],
        ];

        foreach ($workerResults as $index => $result) {
            if (!isset($targets[$index])) {
                continue;
            }

            $target = $targets[$index];
            $targetId = (int) ($target['id'] ?? 0);
            $targetCleanedSku = (string) ($target['cleaned_sku'] ?? '');
            $built = $this->buildOutput([$result], false, $targetId);

            foreach ($built['products'] ?? [] as $product) {
                $product['id'] = $targetId;
                $product['sku'] = $targetCleanedSku;
                $product['cleaned_sku'] = $targetCleanedSku;
                $product['original_sku'] = (string) ($target['original_sku'] ?? $product['original_sku'] ?? '');
                $product['excel_category'] = (string) ($target['excel_category'] ?? $product['excel_category'] ?? '');
                $product['type'] = (string) ($target['type'] ?? $product['type'] ?? '');
                $product['product_url'] = (string) ($target['product_url'] ?? $product['product_url'] ?? '');
                $product = $this->mergeProductMetadata($product, $target);
                $output['products'][] = $product;
            }

            foreach ($built['options'] ?? [] as $option) {
                $option['product_id'] = $targetId;
                $option['sku'] = $targetCleanedSku;
                $output['options'][] = $option;
            }
        }

        return $output;
    }

    private function zeroOptionRetryTargets(array $targets, array $rerunOutput)
    {
        $optionCounts = $this->optionCountsByProductId($rerunOutput);
        $retryTargets = [];

        foreach ($targets as $target) {
            $targetId = (int) ($target['id'] ?? 0);
            $previousOptionsCount = (int) ($target['options_count'] ?? 0);

            if ($targetId > 0 && $previousOptionsCount > 0 && ($optionCounts[$targetId] ?? 0) === 0) {
                $retryTargets[] = $target;
            }
        }

        return $retryTargets;
    }

    private function replaceRerunOutputProducts(array $output, array $retryOutput)
    {
        $replacements = [];

        foreach ($retryOutput['products'] ?? [] as $product) {
            $id = (int) ($product['id'] ?? 0);

            if ($id > 0) {
                $replacements[$id] = [
                    'product' => $product,
                    'options' => [],
                ];
            }
        }

        foreach ($retryOutput['options'] ?? [] as $option) {
            $id = (int) ($option['product_id'] ?? 0);

            if ($id > 0 && isset($replacements[$id])) {
                $replacements[$id]['options'][] = $option;
            }
        }

        return $this->replaceOutputProducts($output, $replacements);
    }

    private function mergeProductMetadata(array $product, array $existingProduct)
    {
        foreach (['sku', 'original_sku', 'cleaned_sku', 'excel_category', 'type'] as $field) {
            if (trim((string) ($product[$field] ?? '')) === '' && trim((string) ($existingProduct[$field] ?? '')) !== '') {
                $product[$field] = $existingProduct[$field];
            }
        }

        if (trim((string) ($product['product_url'] ?? '')) === '' && trim((string) ($existingProduct['product_url'] ?? '')) !== '') {
            $product['product_url'] = $existingProduct['product_url'];
        }

        return $product;
    }

    private function optionCountsByProductId(array $output)
    {
        $counts = [];

        foreach ($output['options'] ?? [] as $option) {
            $productId = (int) ($option['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (!isset($counts[$productId])) {
                $counts[$productId] = 0;
            }

            $counts[$productId]++;
        }

        return $counts;
    }

    private function findOptionImageConflicts(array $output, array $targetMap = [])
    {
        $optionsByProduct = [];

        foreach ($output['options'] ?? [] as $option) {
            $productId = (int) ($option['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (!isset($optionsByProduct[$productId])) {
                $optionsByProduct[$productId] = [];
            }

            $optionsByProduct[$productId][] = [
                'option_name' => (string) ($option['option_name'] ?? ''),
                'image_value' => (string) ($option['image_value'] ?? ''),
                'source_image_url' => (string) ($option['source_image_url'] ?? ''),
            ];
        }

        $byCleanedSku = [];

        foreach ($output['products'] ?? [] as $product) {
            $productId = (int) ($product['id'] ?? 0);
            $cleanedSku = trim((string) ($product['cleaned_sku'] ?? $product['sku'] ?? ''));

            if ($productId <= 0 || $cleanedSku === '' || !isset($optionsByProduct[$productId])) {
                continue;
            }

            if (!empty($targetMap) && !isset($targetMap[$cleanedSku])) {
                continue;
            }

            if (!isset($byCleanedSku[$cleanedSku])) {
                $byCleanedSku[$cleanedSku] = [];
            }

            $items = $optionsByProduct[$productId];
            $byCleanedSku[$cleanedSku][] = [
                'id' => $productId,
                'original_sku' => (string) ($product['original_sku'] ?? ''),
                'cleaned_sku' => $cleanedSku,
                'product_url' => (string) ($product['product_url'] ?? ''),
                'plugin' => (string) ($product['plugin'] ?? ''),
                'status' => (string) ($product['status'] ?? ''),
                'error' => $product['error'] ?? null,
                'options_count' => count($items),
                'signature' => $this->optionImageSignature($items),
            ];
        }

        $conflicts = [];

        foreach ($byCleanedSku as $cleanedSku => $products) {
            $originalSkus = [];
            $signatures = [];

            foreach ($products as $product) {
                $originalSkus[$product['original_sku']] = true;
                $signatures[$product['signature']] = true;
            }

            if (count($products) <= 1 || count($signatures) <= 1 || count($originalSkus) <= 1) {
                continue;
            }

            $groups = [];

            foreach ($products as $product) {
                $signature = $product['signature'];

                if (!isset($groups[$signature])) {
                    $groups[$signature] = [];
                }

                unset($product['signature']);
                $groups[$signature][] = $product;
            }

            $conflicts[$cleanedSku] = array_values($groups);
        }

        ksort($conflicts);

        return $conflicts;
    }

    private function groupOptionImageProducts(array $output, array $targetMap = [])
    {
        $optionsByProduct = [];

        foreach ($output['options'] ?? [] as $option) {
            $productId = (int) ($option['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (!isset($optionsByProduct[$productId])) {
                $optionsByProduct[$productId] = [];
            }

            $optionsByProduct[$productId][] = [
                'option_name' => (string) ($option['option_name'] ?? ''),
                'image_value' => (string) ($option['image_value'] ?? ''),
                'source_image_url' => (string) ($option['source_image_url'] ?? ''),
            ];
        }

        $groups = [];

        foreach ($output['products'] ?? [] as $product) {
            $productId = (int) ($product['id'] ?? 0);
            $cleanedSku = trim((string) ($product['cleaned_sku'] ?? $product['sku'] ?? ''));

            if ($productId <= 0 || $cleanedSku === '') {
                continue;
            }

            if (!empty($targetMap) && !isset($targetMap[$cleanedSku])) {
                continue;
            }

            $items = $optionsByProduct[$productId] ?? [];
            $signature = $this->optionImageSignature($items);

            if (!isset($groups[$cleanedSku])) {
                $groups[$cleanedSku] = [];
            }

            if (!isset($groups[$cleanedSku][$signature])) {
                $groups[$cleanedSku][$signature] = [];
            }

            $groups[$cleanedSku][$signature][] = [
                'id' => $productId,
                'original_sku' => (string) ($product['original_sku'] ?? ''),
                'cleaned_sku' => $cleanedSku,
                'product_url' => (string) ($product['product_url'] ?? ''),
                'plugin' => (string) ($product['plugin'] ?? ''),
                'status' => (string) ($product['status'] ?? ''),
                'error' => $product['error'] ?? null,
                'options_count' => count($items),
            ];
        }

        ksort($groups);

        return array_map(function ($signatureGroups) {
            return array_values($signatureGroups);
        }, $groups);
    }

    private function optionImageSignature(array $items)
    {
        usort($items, function ($a, $b) {
            $left = implode("\x1F", [
                $a['option_name'] ?? '',
                $a['image_value'] ?? '',
                $a['source_image_url'] ?? '',
            ]);
            $right = implode("\x1F", [
                $b['option_name'] ?? '',
                $b['image_value'] ?? '',
                $b['source_image_url'] ?? '',
            ]);

            return strcmp($left, $right);
        });

        return sha1(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function productsFromConflicts(array $conflicts)
    {
        $products = [];
        $seen = [];

        foreach ($conflicts as $groups) {
            foreach ($groups as $group) {
                foreach ($group as $product) {
                    $id = (int) ($product['id'] ?? 0);
                    $url = trim((string) ($product['product_url'] ?? ''));

                    if ($id <= 0 || $url === '' || isset($seen[$id])) {
                        continue;
                    }

                    $products[] = $product;
                    $seen[$id] = true;
                }
            }
        }

        return $products;
    }

    private function formatConflictReportMarkdown(array $currentConflicts, array $rerunConflicts, array $rerunGroups, $includeRerun)
    {
        $lines = [
            '# SKU Option Image Conflict Report',
            '',
            'Generated at: ' . date('c'),
            '',
            'Current conflict count: ' . count($currentConflicts),
        ];

        if ($includeRerun) {
            $lines[] = 'Rerun conflict count: ' . count($rerunConflicts);
        }

        $lines[] = '';
        $lines[] = '## Current JSON Conflicts';
        $lines[] = '';
        $this->appendConflictMarkdown($lines, $currentConflicts);

        if ($includeRerun) {
            $lines[] = '';
            $lines[] = '## Rerun Product Groups';
            $lines[] = '';
            $this->appendConflictMarkdown($lines, $rerunGroups);
            $lines[] = '';
            $lines[] = '## Rerun Conflicts';
            $lines[] = '';
            $this->appendConflictMarkdown($lines, $rerunConflicts);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function appendConflictMarkdown(array &$lines, array $conflicts)
    {
        if (empty($conflicts)) {
            $lines[] = 'No conflicts found.';
            return;
        }

        foreach ($conflicts as $cleanedSku => $groups) {
            $lines[] = '### ' . $cleanedSku;

            foreach ($groups as $index => $group) {
                $lines[] = 'Group ' . ($index + 1) . ' (' . count($group) . ' product record(s))';

                foreach ($group as $product) {
                    $lines[] = '- product_id=' . ($product['id'] ?? '')
                        . ' | original_sku=' . ($product['original_sku'] ?? '')
                        . ' | options=' . ($product['options_count'] ?? 0)
                        . ' | plugin=' . ($product['plugin'] ?? '')
                        . ' | status=' . ($product['status'] ?? '')
                        . ' | error=' . ($product['error'] ?? '')
                        . ' | url=' . ($product['product_url'] ?? '');
                }
            }

            $lines[] = '';
        }
    }

    public function outputRepairCandidateIds($includeConnectionClosed = false, $includeUnknownSku = false, $includeUnknownFailed = false)
    {
        $output = $this->loadOutput();
        $ids = [];

        foreach ($output['products'] ?? [] as $product) {
            $id = (int) ($product['id'] ?? 0);
            $error = (string) ($product['error'] ?? '');
            $sku = trim((string) ($product['sku'] ?? ''));
            $plugin = (string) ($product['plugin'] ?? '');

            if ($id <= 0) {
                continue;
            }

            if ($includeConnectionClosed && strpos($error, 'ERR_CONNECTION_CLOSED') !== false) {
                $ids[] = $id;
                continue;
            }

            if ($includeUnknownFailed && $plugin === 'unknown' && ($product['status'] ?? '') === 'failed') {
                $ids[] = $id;
                continue;
            }

            if (
                $includeUnknownSku
                && $sku !== ''
                && $plugin === 'unknown'
                && $error === 'No supported option plugin was detected.'
            ) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function buildOutput(array $workerResults, $downloadImages = false, $startingProductId = 1, $startingSortOrder = 1)
    {
        $products = [];
        $options = [];
        $productId = (int) $startingProductId;

        foreach ($workerResults as $result) {
            if (($result['status'] ?? '') === 'skipped') {
                continue;
            }

            $rawSku = trim((string) ($result['sku'] ?? ''));
            $sku = $this->skuCleaningService->resolve($rawSku);
            $cleanedSku = $sku['cleaned_sku'];

            $products[] = [
                'id' => $productId,
                'sku' => $cleanedSku,
                'original_sku' => $sku['original_sku'],
                'cleaned_sku' => $cleanedSku,
                'excel_category' => $sku['excel_category'],
                'type' => $sku['type'],
                'product_url' => (string) ($result['url'] ?? ''),
                'plugin' => (string) ($result['plugin'] ?? 'unknown'),
                'status' => (string) ($result['status'] ?? 'failed'),
                'error' => $result['error'] ?? null,
            ];

            $sortOrder = 1;

            foreach (($result['options'] ?? []) as $option) {
                $optionName = trim((string) ($option['name'] ?? ''));

                if ($optionName === '') {
                    continue;
                }

                foreach (($option['values'] ?? []) as $value) {
                    $imageValue = trim((string) ($value['image_value'] ?? ''));
                    $sourceImageUrl = trim((string) ($value['source_image_url'] ?? ''));
                    $extension = $this->resolveImageExtension($value, $sourceImageUrl);
                    $relativeImagePath = $this->makeImagePath($cleanedSku, $imageValue, $extension);
                    $downloadError = null;

                    if ($downloadImages && $sourceImageUrl !== '') {
                        $downloadError = $this->downloadImage($sourceImageUrl, $this->imageDirectory . DIRECTORY_SEPARATOR . basename($relativeImagePath));
                    }

                    $optionRow = [
                        'product_id' => $productId,
                        'sort_order' => $sortOrder,
                        'sku' => $cleanedSku,
                        'option_name' => $optionName,
                        'image_value' => $imageValue,
                        'image_path' => '/sku-options-image/' . basename($relativeImagePath),
                        'source_image_url' => $sourceImageUrl,
                    ];

                    if ($downloadError !== null) {
                        $optionRow['error'] = $downloadError;
                    }

                    $options[] = $optionRow;
                    $sortOrder++;
                }
            }

            $productId++;
        }

        return [
            'products' => $products,
            'options' => $options,
        ];
    }

    private function loadOutputForResume($startIndex)
    {
        if ((int) $startIndex <= 1 || !file_exists($this->outputJsonPath)) {
            return [
                'products' => [],
                'options' => [],
            ];
        }

        $data = json_decode(file_get_contents($this->outputJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [
                'products' => [],
                'options' => [],
            ];
        }

        return [
            'products' => is_array($data['products'] ?? null) ? $data['products'] : [],
            'options' => is_array($data['options'] ?? null) ? $data['options'] : [],
        ];
    }

    private function loadOutput()
    {
        if (!file_exists($this->outputJsonPath)) {
            return [
                'products' => [],
                'options' => [],
            ];
        }

        $data = json_decode(file_get_contents($this->outputJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \Exception('Invalid SKU option output JSON: ' . json_last_error_msg());
        }

        return [
            'products' => is_array($data['products'] ?? null) ? $data['products'] : [],
            'options' => is_array($data['options'] ?? null) ? $data['options'] : [],
        ];
    }

    private function mergeOutput(array $existing, array $batch)
    {
        return [
            'products' => array_merge($existing['products'] ?? [], $batch['products'] ?? []),
            'options' => array_merge($existing['options'] ?? [], $batch['options'] ?? []),
        ];
    }

    private function replaceOutputProducts(array $output, array $replacements)
    {
        $replacementOptions = [];
        $replacedIds = array_map('intval', array_keys($replacements));
        $products = [];

        foreach ($output['products'] ?? [] as $product) {
            $id = (int) ($product['id'] ?? 0);

            if (!array_key_exists($id, $replacements)) {
                $products[] = $product;
                continue;
            }

            if (!empty($replacements[$id]['product'])) {
                $products[] = $replacements[$id]['product'];
            }

            foreach ($replacements[$id]['options'] ?? [] as $option) {
                $replacementOptions[] = $option;
            }
        }

        $options = array_values(array_filter($output['options'] ?? [], function ($option) use ($replacedIds) {
            return !in_array((int) ($option['product_id'] ?? 0), $replacedIds, true);
        }));

        return [
            'products' => $products,
            'options' => array_merge($options, $replacementOptions),
        ];
    }

    private function normalizeOutputSortOrders(array $output)
    {
        $counters = [];
        $options = [];

        foreach ($output['options'] ?? [] as $option) {
            $productId = (int) ($option['product_id'] ?? 0);

            if ($productId <= 0) {
                $options[] = $option;
                continue;
            }

            if (!isset($counters[$productId])) {
                $counters[$productId] = 1;
            }

            $option['sort_order'] = $counters[$productId];
            $counters[$productId]++;
            $options[] = $option;
        }

        return [
            'products' => $output['products'] ?? [],
            'options' => $options,
        ];
    }

    private function nextProductId(array $output)
    {
        $ids = array_map(function ($product) {
            return (int) ($product['id'] ?? 0);
        }, $output['products'] ?? []);

        return empty($ids) ? 1 : max($ids) + 1;
    }

    private function nextSortOrder(array $output)
    {
        $orders = array_map(function ($option) {
            return (int) ($option['sort_order'] ?? 0);
        }, $output['options'] ?? []);

        return empty($orders) ? 1 : max($orders) + 1;
    }

    private function writeOutput(array $output)
    {
        $this->ensureDirectory(dirname($this->outputJsonPath));
        file_put_contents($this->outputJsonPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function writeProgress($progressPath, array $progress)
    {
        $this->ensureDirectory(dirname($progressPath));
        file_put_contents($progressPath, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function logScrapeErrors(array $output)
    {
        foreach ($output['products'] ?? [] as $product) {
            if (($product['status'] ?? '') === 'completed' && empty($product['error'])) {
                continue;
            }

            Log::warning('SKU option product scrape issue.', [
                'product_id' => $product['id'] ?? null,
                'url' => $product['product_url'] ?? null,
                'sku' => $product['sku'] ?? null,
                'plugin' => $product['plugin'] ?? null,
                'status' => $product['status'] ?? null,
                'error' => $product['error'] ?? null,
            ]);
        }

        foreach ($output['options'] ?? [] as $option) {
            if (empty($option['error'])) {
                continue;
            }

            Log::warning('SKU option image download issue.', [
                'product_id' => $option['product_id'] ?? null,
                'sort_order' => $option['sort_order'] ?? null,
                'sku' => $option['sku'] ?? null,
                'option_name' => $option['option_name'] ?? null,
                'image_value' => $option['image_value'] ?? null,
                'source_image_url' => $option['source_image_url'] ?? null,
                'image_path' => $option['image_path'] ?? null,
                'error' => $option['error'] ?? null,
            ]);
        }
    }

    private function makeImagePath($sku, $imageValue, $extension)
    {
        $base = implode('_', array_filter([
            $this->slug($sku),
            $this->slug($imageValue),
        ]));

        if ($base === '') {
            $base = 'sku-option-image';
        }

        $extension = trim((string) $extension, '. ');
        $extension = $extension === '' ? 'image' : strtolower($extension);

        return $base . '.' . $extension;
    }

    private function slug($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    private function resolveImageExtension(array $value, $sourceImageUrl)
    {
        $extension = strtolower(trim((string) ($value['extension'] ?? ''), '. '));

        if ($extension !== '') {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        $path = parse_url($sourceImageUrl, PHP_URL_PATH);
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return in_array($extension, ['png', 'jpg', 'gif', 'webp'], true) ? $extension : 'image';
    }

    private function downloadImage($url, $path)
    {
        try {
            if (file_exists($path)) {
                return null;
            }

            $bytes = $this->fetchUrlBytes($url);

            if ($bytes === null || $bytes === '') {
                return 'Image download returned empty content.';
            }

            $this->ensureDirectory(dirname($path));
            file_put_contents($path, $bytes);

            return null;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function fetchUrlBytes($url)
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);

            $bytes = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($bytes === false || $status >= 400) {
                throw new \Exception($error ?: "HTTP {$status} while downloading image");
            }

            return $bytes;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => "User-Agent: Mozilla/5.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function ensureDirectory($directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function nodeProcessEnvironment()
    {
        $env = [];
        $matches = glob(base_path('node_modules/.pnpm/playwright-core@*/node_modules')) ?: [];
        $playwrightCoreNodeModules = $matches[0] ?? null;

        if ($playwrightCoreNodeModules && is_dir($playwrightCoreNodeModules)) {
            $env['NODE_PATH'] = $playwrightCoreNodeModules;
        }

        $browserPath = $this->systemChromiumExecutable();

        if ($browserPath !== null) {
            $env['PLAYWRIGHT_CHROMIUM_EXECUTABLE'] = $browserPath;
        }

        return empty($env) ? null : $env;
    }

    private function systemChromiumExecutable()
    {
        $paths = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
