<?php

namespace Tests\Unit;

use App\Services\SkuOptionImageResolver;
use Tests\TestCase;

class SkuOptionImageResolverTest extends TestCase
{
    private $tempDirectory;

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null) {
            $this->deleteDirectory($this->tempDirectory);
        }

        parent::tearDown();
    }

    public function test_resolves_option_image_by_cleaned_sku_option_name_and_value()
    {
        $this->tempDirectory = storage_path('app/testing/sku-option-resolver-' . uniqid());
        mkdir($this->tempDirectory . '/sku-options-image', 0755, true);

        $jsonPath = $this->tempDirectory . '/sku-options-image.json';
        $imagePath = $this->tempDirectory . '/sku-options-image/cs-qk0743-cx_california.png';
        file_put_contents($imagePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));
        file_put_contents($jsonPath, json_encode([
            'products' => [
                [
                    'id' => 10,
                    'sku' => 'RAW-CS-QK0743-CX',
                    'cleaned_sku' => 'CS-QK0743-CX',
                ],
            ],
            'options' => [
                [
                    'product_id' => 10,
                    'option_name' => 'Choose State Options',
                    'image_value' => 'California',
                    'image_path' => '/sku-options-image/cs-qk0743-cx_california.png',
                    'source_image_url' => 'https://example.test/fallback.png',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $resolver = new SkuOptionImageResolver($jsonPath);

        $this->assertSame(str_replace('/', DIRECTORY_SEPARATOR, $imagePath), $resolver->resolve('CS-QK0743-CX', 'Choose State Options', 'California'));
        $this->assertSame('', $resolver->resolve('CS-QK0743-CX', 'Choose State Options', 'Texas'));
    }

    public function test_falls_back_to_source_image_url_when_local_image_is_missing()
    {
        $this->tempDirectory = storage_path('app/testing/sku-option-resolver-' . uniqid());
        mkdir($this->tempDirectory, 0755, true);

        $jsonPath = $this->tempDirectory . '/sku-options-image.json';
        file_put_contents($jsonPath, json_encode([
            'products' => [
                [
                    'id' => 11,
                    'cleaned_sku' => 'CS-QK0743-CX',
                ],
            ],
            'options' => [
                [
                    'product_id' => 11,
                    'option_name' => '2nd Line Font',
                    'image_value' => 'F4',
                    'image_path' => '/sku-options-image/missing.png',
                    'source_image_url' => 'https://example.test/font-f4.png',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $resolver = new SkuOptionImageResolver($jsonPath);

        $this->assertSame('https://example.test/font-f4.png', $resolver->resolve('CS-QK0743-CX', '2nd Line Font', 'F4'));
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
