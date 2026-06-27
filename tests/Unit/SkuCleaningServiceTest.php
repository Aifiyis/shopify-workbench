<?php

namespace Tests\Unit;

use App\Services\SkuCleaningService;
use Tests\TestCase;

class SkuCleaningServiceTest extends TestCase
{
    private $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = storage_path('app/testing/sku-cleaning-' . uniqid());
        mkdir($this->tempDirectory, 0755, true);

        file_put_contents($this->tempDirectory . '/sku-cleaned.json', json_encode([
            [
                'original_sku' => 'RAW-QK1000-Red-XL',
                'cleaned_sku' => 'RAW-QK1000',
                '中文名称' => '领口破洞刺绣',
            ],
            [
                'original_sku' => 'ANY-QK2000-Blue',
                'cleaned_sku' => 'ANY-QK2000',
                '中文名称' => '袖口刺绣',
            ],
        ], JSON_UNESCAPED_UNICODE));

        file_put_contents($this->tempDirectory . '/sku-exclude-values.json', json_encode([
            'all_exclude_values' => ['Red', 'Blue', 'XL'],
        ], JSON_UNESCAPED_UNICODE));

        file_put_contents($this->tempDirectory . '/sku-exclude-rule-patterns.json', json_encode([
            'include_type' => [
                'fields' => [
                    [
                        'value' => 'kid',
                        'regex' => '/kid/iu',
                    ],
                ],
            ],
            'equals_type' => [
                'fields' => [],
            ],
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function test_resolves_exact_original_sku_before_cleaning()
    {
        $service = new SkuCleaningService(
            $this->tempDirectory . '/sku-cleaned.json',
            $this->tempDirectory . '/sku-exclude-values.json'
        );

        $result = $service->resolve('RAW-QK1000-Red-XL');

        $this->assertSame('RAW-QK1000-Red-XL', $result['original_sku']);
        $this->assertSame('RAW-QK1000', $result['cleaned_sku']);
        $this->assertSame('领口破洞刺绣', $result['excel_category']);
        $this->assertSame('领口破洞刺绣', $result['type']);
    }

    public function test_cleans_unknown_original_sku_and_matches_cleaned_sku_name()
    {
        $service = new SkuCleaningService(
            $this->tempDirectory . '/sku-cleaned.json',
            $this->tempDirectory . '/sku-exclude-values.json'
        );

        $result = $service->resolve('ANY-QK2000-Blue-XL');

        $this->assertSame('ANY-QK2000-Blue-XL', $result['original_sku']);
        $this->assertSame('ANY-QK2000', $result['cleaned_sku']);
        $this->assertSame('袖口刺绣', $result['excel_category']);
        $this->assertSame('袖口刺绣', $result['type']);
    }

    public function test_cleans_sku_with_values_before_applying_rule_patterns()
    {
        file_put_contents($this->tempDirectory . '/sku-exclude-values.json', json_encode([
            'all_exclude_values' => ['Beige'],
        ], JSON_UNESCAPED_UNICODE));

        $service = new SkuCleaningService(
            $this->tempDirectory . '/sku-cleaned.json',
            $this->tempDirectory . '/sku-exclude-values.json',
            $this->tempDirectory . '/sku-exclude-rule-patterns.json'
        );

        $this->assertSame(
            'ACC-QK6713-CX',
            $service->cleanSkuUsingValuesAndPatterns('ACC-QK6713-Beige/50-kid-CX')
        );
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
}
