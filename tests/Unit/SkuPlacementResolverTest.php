<?php

namespace Tests\Unit;

use App\Services\SkuPlacementResolver;
use Tests\TestCase;

class SkuPlacementResolverTest extends TestCase
{
    private $tempDirectory;

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null) {
            $this->deleteDirectory($this->tempDirectory);
        }

        parent::tearDown();
    }

    public function test_resolves_fixed_position_by_cleaned_sku_and_website()
    {
        $resolver = new SkuPlacementResolver($this->writePlacementRules([
            [
                'website' => 'broderft.com',
                'position' => '左胸口',
                'cleaned_skus' => ['CS-QK3312-TH', 'CS-QK3312-TH', 'CS-QK3313-TH'],
            ],
            [
                'website' => 'broderft.com',
                'position' => '背部',
                'cleaned_skus' => ['CS-QK0399-TH'],
            ],
            [
                'website' => 'embroiderely.com',
                'position' => '右胸口',
                'cleaned_skus' => ['CS-QK2594-CX'],
            ],
        ]));

        $this->assertSame('左胸口', $resolver->resolve('cs-qk3312-th', 'broderft.com'));
        $this->assertSame('背部', $resolver->resolve('CS-QK0399-TH', 'broderft.com'));
        $this->assertSame('右胸口', $resolver->resolve('CS-QK2594-CX', 'embroiderely.com'));
        $this->assertSame('', $resolver->resolve('CS-QK2594-CX', 'broderft.com'));
        $this->assertSame('', $resolver->resolve('UNKNOWN-SKU', 'broderft.com'));
    }

    public function test_uses_sku_options_image_website_when_website_is_not_provided()
    {
        $placementRulesPath = $this->writePlacementRules([
            [
                'website' => 'broderft.com',
                'position' => '左胸口',
                'cleaned_skus' => ['CS-QK3312-TH'],
            ],
            [
                'website' => 'embroiderely.com',
                'position' => '右胸口',
                'cleaned_skus' => ['CS-QK3312-TH'],
            ],
        ]);
        $skuOptionsImagePath = $this->writeSkuOptionsImageProducts([
            [
                'id' => 10,
                'cleaned_sku' => 'CS-QK3312-TH',
                'website' => 'embroiderely.com',
            ],
        ]);

        $resolver = new SkuPlacementResolver($placementRulesPath, $skuOptionsImagePath);

        $this->assertSame('右胸口', $resolver->resolve('CS-QK3312-TH'));
    }

    public function test_resolves_rule_metadata_with_placement_behavior()
    {
        $resolver = new SkuPlacementResolver($this->writePlacementRules([
            [
                'website' => 'broderft.com',
                'position' => '左胸和后背',
                'placement_behavior' => 'mirror_chest_to_back',
                'cleaned_skus' => ['CS-LXJ7445-TH'],
            ],
        ]));

        $rule = $resolver->resolveRule('CS-LXJ7445-TH', 'broderft.com');

        $this->assertSame('左胸和后背', $resolver->resolve('CS-LXJ7445-TH', 'broderft.com'));
        $this->assertSame('左胸和后背', $rule['position'] ?? '');
        $this->assertSame('mirror_chest_to_back', $rule['placement_behavior'] ?? '');
        $this->assertSame('broderft.com', $rule['website'] ?? '');
    }

    public function test_default_path_uses_private_lookups_placement_rules()
    {
        $reflection = new \ReflectionClass(SkuPlacementResolver::class);
        $method = $reflection->getMethod('defaultPlacementRulesPath');
        $method->setAccessible(true);
        $resolver = new SkuPlacementResolver();
        $rule = $resolver->resolveRule('CS-LXJ7445-TH', 'broderft.com');

        $this->assertSame(storage_path('app/private/lookups/sku-placement-rules.json'), $method->invoke($resolver));
        $this->assertSame('左胸和后背', $rule['position'] ?? '');
        $this->assertSame('mirror_chest_to_back', $rule['placement_behavior'] ?? '');
    }

    private function writePlacementRules(array $rules)
    {
        $directory = $this->makeTempDirectory();
        $path = $directory . '/sku-placement-rules.json';

        file_put_contents($path, json_encode(['rules' => $rules], JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function writeSkuOptionsImageProducts(array $products)
    {
        $directory = $this->tempDirectory ?: $this->makeTempDirectory();
        $path = $directory . '/sku-options-image.json';

        file_put_contents($path, json_encode([
            'products' => $products,
            'options' => [],
        ], JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function makeTempDirectory()
    {
        if ($this->tempDirectory !== null) {
            return $this->tempDirectory;
        }

        $this->tempDirectory = storage_path('app/testing/sku-placement-resolver-' . uniqid());
        mkdir($this->tempDirectory, 0755, true);

        return $this->tempDirectory;
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
