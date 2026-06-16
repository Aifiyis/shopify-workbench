<?php

namespace Tests\Unit;

use App\Services\SkuCleaningService;
use App\Services\SkuOptionScrapeService;
use Tests\TestCase;

class SkuOptionScrapeServiceTest extends TestCase
{
    private $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = storage_path('app/testing/sku-options-' . uniqid());
        mkdir($this->tempDirectory, 0755, true);

        file_put_contents($this->tempDirectory . '/sku-cleaned.json', json_encode([
            [
                'original_sku' => 'TEST-QK1000-Red',
                'cleaned_sku' => 'TEST-QK1000',
                '中文名称' => '领口破洞刺绣',
            ],
        ], JSON_UNESCAPED_UNICODE));

        file_put_contents($this->tempDirectory . '/sku-exclude-values.json', json_encode([
            'all_exclude_values' => ['Red'],
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function test_builds_normalized_output_with_sort_order_and_value_based_image_paths()
    {
        $service = new SkuOptionScrapeService(
            new SkuCleaningService(
                $this->tempDirectory . '/sku-cleaned.json',
                $this->tempDirectory . '/sku-exclude-values.json'
            ),
            $this->tempDirectory . '/sku-options-image',
            $this->tempDirectory . '/sku-options-image.json',
            base_path('missing-worker.js')
        );

        $output = $service->buildOutput([
            [
                'url' => 'https://example.test/products/test',
                'plugin' => 'ymq',
                'status' => 'completed',
                'sku' => 'TEST-QK1000-Red',
                'options' => [
                    [
                        'name' => 'Left Sleeve Icon',
                        'values' => [
                            [
                                'image_value' => 'Names with Heart',
                                'source_image_url' => 'https://cdn.example.test/heart.png',
                                'extension' => 'png',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Right Sleeve Icon',
                        'values' => [
                            [
                                'image_value' => 'Names with Heart',
                                'source_image_url' => 'https://cdn.example.test/heart-right.png',
                                'extension' => 'png',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $output['products']);
        $this->assertSame('TEST-QK1000', $output['products'][0]['sku']);
        $this->assertSame('领口破洞刺绣', $output['products'][0]['excel_category']);
        $this->assertSame('领口破洞刺绣', $output['products'][0]['type']);
        $this->assertCount(2, $output['options']);
        $this->assertSame(1, $output['options'][0]['product_id']);
        $this->assertSame(1, $output['options'][0]['sort_order']);
        $this->assertSame(2, $output['options'][1]['sort_order']);
        $this->assertSame($output['options'][0]['image_path'], $output['options'][1]['image_path']);
        $this->assertSame('/sku-options-image/test-qk1000_names-with-heart.png', $output['options'][0]['image_path']);
    }

    public function test_appends_batch_output_with_continuing_ids_and_product_scoped_sort_order()
    {
        $service = new SkuOptionScrapeService(
            new SkuCleaningService(
                $this->tempDirectory . '/sku-cleaned.json',
                $this->tempDirectory . '/sku-exclude-values.json'
            ),
            $this->tempDirectory . '/sku-options-image',
            $this->tempDirectory . '/sku-options-image.json',
            base_path('missing-worker.js')
        );

        $existing = [
            'products' => [
                [
                    'id' => 1,
                    'sku' => 'OLD-SKU',
                    'original_sku' => 'OLD-SKU',
                    'cleaned_sku' => 'OLD-SKU',
                    'excel_category' => '',
                    'type' => '',
                    'product_url' => 'https://example.test/old',
                    'plugin' => 'ymq',
                    'status' => 'completed',
                    'error' => null,
                ],
            ],
            'options' => [
                ['product_id' => 1, 'sort_order' => 1, 'sku' => 'OLD-SKU'],
                ['product_id' => 1, 'sort_order' => 2, 'sku' => 'OLD-SKU'],
            ],
        ];

        $output = $service->appendWorkerResults($existing, [
            [
                'url' => 'https://example.test/products/test',
                'plugin' => 'ymq',
                'status' => 'completed',
                'sku' => 'TEST-QK1000-Red',
                'options' => [
                    [
                        'name' => 'Left Sleeve Icon',
                        'values' => [
                            [
                                'image_value' => 'Smile',
                                'source_image_url' => 'https://cdn.example.test/smile.png',
                                'extension' => 'png',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $output['products']);
        $this->assertSame(2, $output['products'][1]['id']);
        $this->assertCount(3, $output['options']);
        $this->assertSame(2, $output['options'][2]['product_id']);
        $this->assertSame(1, $output['options'][2]['sort_order']);
        $this->assertSame('/sku-options-image/test-qk1000_smile.png', $output['options'][2]['image_path']);
    }

    public function test_skips_worker_results_marked_as_skipped()
    {
        $service = new SkuOptionScrapeService(
            new SkuCleaningService(
                $this->tempDirectory . '/sku-cleaned.json',
                $this->tempDirectory . '/sku-exclude-values.json'
            ),
            $this->tempDirectory . '/sku-options-image',
            $this->tempDirectory . '/sku-options-image.json',
            base_path('missing-worker.js')
        );

        $output = $service->buildOutput([
            [
                'url' => 'https://example.test/products/missing',
                'plugin' => 'unknown',
                'status' => 'skipped',
                'sku' => '',
                'options' => [],
                'error' => 'Page appears to be 404 and no SKU was found.',
            ],
            [
                'url' => 'https://example.test/products/test',
                'plugin' => 'ymq',
                'status' => 'completed',
                'sku' => 'TEST-QK1000-Red',
                'options' => [],
            ],
        ]);

        $this->assertCount(1, $output['products']);
        $this->assertSame(1, $output['products'][0]['id']);
        $this->assertSame('https://example.test/products/test', $output['products'][0]['product_url']);
        $this->assertCount(0, $output['options']);
    }

    public function test_repair_preserves_existing_sku_metadata_when_rerun_returns_empty_sku()
    {
        $workerPath = $this->tempDirectory . '/empty-sku-worker.js';
        $this->writeWorkerScript($workerPath, <<<'JS'
const fs = require('fs');
const inputPath = process.argv[process.argv.indexOf('--input') + 1];
const outputPath = process.argv[process.argv.indexOf('--output') + 1];
const input = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
fs.writeFileSync(outputPath, JSON.stringify({
  results: input.urls.map((url) => ({
    url,
    plugin: 'unknown',
    status: 'failed',
    sku: '',
    options: [],
    error: 'net::ERR_CONNECTION_CLOSED'
  }))
}, null, 2));
JS
        );

        $outputPath = $this->tempDirectory . '/sku-options-image.json';
        file_put_contents($outputPath, json_encode([
            'products' => [
                [
                    'id' => 5,
                    'sku' => 'OLD-CLEANED',
                    'original_sku' => 'OLD-ORIGINAL-Red',
                    'cleaned_sku' => 'OLD-CLEANED',
                    'excel_category' => 'Old Category',
                    'type' => 'Old Type',
                    'product_url' => 'https://example.test/products/retry',
                    'plugin' => 'unknown',
                    'status' => 'failed',
                    'error' => 'No supported option plugin was detected.',
                ],
            ],
            'options' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $service = $this->makeService($outputPath, $workerPath);

        $service->repairOutputProductIds([5], 120);

        $output = json_decode(file_get_contents($outputPath), true);
        $product = $output['products'][0];

        $this->assertSame('OLD-CLEANED', $product['sku']);
        $this->assertSame('OLD-ORIGINAL-Red', $product['original_sku']);
        $this->assertSame('OLD-CLEANED', $product['cleaned_sku']);
        $this->assertSame('Old Category', $product['excel_category']);
        $this->assertSame('Old Type', $product['type']);
        $this->assertSame('net::ERR_CONNECTION_CLOSED', $product['error']);
    }

    public function test_conflict_report_reruns_zero_option_products_once_and_writes_markdown()
    {
        $statePath = str_replace('\\', '/', $this->tempDirectory . '/worker-state.json');
        $workerPath = $this->tempDirectory . '/retry-once-worker.js';
        $this->writeWorkerScript($workerPath, <<<JS
const fs = require('fs');
const inputPath = process.argv[process.argv.indexOf('--input') + 1];
const outputPath = process.argv[process.argv.indexOf('--output') + 1];
const input = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
let state = {};
if (fs.existsSync('{$statePath}')) {
  state = JSON.parse(fs.readFileSync('{$statePath}', 'utf8'));
}
const results = input.urls.map((url) => {
  state[url] = (state[url] || 0) + 1;
  if (url.includes('retry-once') && state[url] === 1) {
    return { url, plugin: 'unknown', status: 'failed', sku: 'TEST-QK1000-Blue', options: [], error: 'No supported option plugin was detected.' };
  }
  return {
    url,
    plugin: 'ymq',
    status: 'completed',
    sku: url.includes('retry-once') ? 'TEST-QK1000-Blue' : 'TEST-QK1000-Red',
    options: [
      {
        name: url.includes('retry-once') ? 'Right Sleeve Icon' : 'Left Sleeve Icon',
        values: [
          {
            image_value: url.includes('retry-once') ? 'Moon' : 'Sun',
            source_image_url: url.includes('retry-once') ? 'https://cdn.example.test/moon.png' : 'https://cdn.example.test/sun.png',
            extension: 'png'
          }
        ]
      }
    ],
    error: null
  };
});
fs.writeFileSync('{$statePath}', JSON.stringify(state, null, 2));
fs.writeFileSync(outputPath, JSON.stringify({ results }, null, 2));
JS
        );

        $outputPath = $this->tempDirectory . '/sku-options-image.json';
        file_put_contents($outputPath, json_encode([
            'products' => [
                [
                    'id' => 1,
                    'sku' => 'TEST-QK1000',
                    'original_sku' => 'TEST-QK1000-Red',
                    'cleaned_sku' => 'TEST-QK1000',
                    'excel_category' => 'Category',
                    'type' => 'Category',
                    'product_url' => 'https://example.test/products/current',
                    'plugin' => 'ymq',
                    'status' => 'completed',
                    'error' => null,
                ],
                [
                    'id' => 2,
                    'sku' => 'TEST-QK1000',
                    'original_sku' => 'TEST-QK1000-Blue',
                    'cleaned_sku' => 'TEST-QK1000',
                    'excel_category' => 'Category',
                    'type' => 'Category',
                    'product_url' => 'https://example.test/products/retry-once',
                    'plugin' => 'ymq',
                    'status' => 'completed',
                    'error' => null,
                ],
            ],
            'options' => [
                ['product_id' => 1, 'sort_order' => 1, 'sku' => 'TEST-QK1000', 'option_name' => 'Left Sleeve Icon', 'image_value' => 'Sun', 'source_image_url' => 'https://cdn.example.test/sun.png'],
                ['product_id' => 2, 'sort_order' => 1, 'sku' => 'TEST-QK1000', 'option_name' => 'Right Sleeve Icon', 'image_value' => 'Old Moon', 'source_image_url' => 'https://cdn.example.test/old-moon.png'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $service = $this->makeService($outputPath, $workerPath);
        $reportPath = $this->tempDirectory . '/conflict-report';

        $result = $service->conflictReport(['TEST-QK1000'], true, 120, $reportPath);

        $this->assertSame($this->tempDirectory . '/conflict-report', $result['report_path']);
        $this->assertFileExists($result['report_path']);
        $this->assertSame(1, $result['rerun_conflict_count']);
        $this->assertStringContainsString('## Rerun Product Groups', $result['markdown']);
        $this->assertStringContainsString('original_sku=TEST-QK1000-Blue', $result['markdown']);
        $this->assertStringContainsString('options=1', $result['markdown']);
    }

    private function deleteDirectory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function makeService($outputPath, $workerPath)
    {
        return new SkuOptionScrapeService(
            new SkuCleaningService(
                $this->tempDirectory . '/sku-cleaned.json',
                $this->tempDirectory . '/sku-exclude-values.json'
            ),
            $this->tempDirectory . '/sku-options-image',
            $outputPath,
            $workerPath
        );
    }

    private function writeWorkerScript($path, $contents)
    {
        file_put_contents($path, $contents);
    }

}
