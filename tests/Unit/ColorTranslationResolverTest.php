<?php

namespace Tests\Unit;

use App\Services\ColorTranslationResolver;
use App\Services\OrderExportTemplates\StyleImageHeatTransferTemplate;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ColorTranslationResolverTest extends TestCase
{
    private $tempDirectory;

    protected function tearDown(): void
    {
        if (is_string($this->tempDirectory) && is_dir($this->tempDirectory)) {
            $this->deleteDirectory($this->tempDirectory);
        }

        parent::tearDown();
    }

    public function test_translates_lookup_miss_and_caches_result()
    {
        $cachePath = $this->cachePath();
        $calls = 0;
        $resolver = new ColorTranslationResolver($cachePath, [
            'enabled' => true,
            'endpoint' => 'https://example.test/get',
            'timeout' => 1,
        ], function ($url, $timeout) use (&$calls) {
            $calls++;

            return json_encode([
                'responseData' => [
                    'translatedText' => '酒红色',
                ],
            ]);
        });

        $this->assertSame('酒红色（翻译原值：Burgundy）', $resolver->translate('Burgundy'));
        $this->assertSame('酒红色（翻译原值：Burgundy）', $resolver->translate(' Burgundy '));
        $this->assertSame(1, $calls);

        $cache = json_decode(file_get_contents($cachePath), true);
        $this->assertSame('酒红色（翻译原值：Burgundy）', $cache['burgundy']);
    }

    public function test_failed_translation_returns_original_value()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(\Mockery::on(function ($message) {
                return strpos($message, 'Color translation fallback failed') !== false;
            }));

        $resolver = new ColorTranslationResolver($this->cachePath(), [
            'enabled' => true,
        ], function () {
            throw new \RuntimeException('network down');
        });

        $this->assertSame('Burgundy', $resolver->translate('Burgundy'));
    }

    public function test_chinese_value_is_returned_without_calling_translation_api()
    {
        $calls = 0;
        $resolver = new ColorTranslationResolver($this->cachePath(), [
            'enabled' => true,
        ], function () use (&$calls) {
            $calls++;

            return '{}';
        });

        $this->assertSame('红色', $resolver->translate('红色'));
        $this->assertSame(0, $calls);
    }

    public function test_lookup_hit_does_not_call_translation_resolver()
    {
        $template = new StyleImageHeatTransferTemplate();
        $translator = new class {
            public $calls = 0;

            public function translate($value)
            {
                $this->calls++;

                return 'SHOULD NOT BE USED';
            }
        };

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-STYLE-LOOKUP',
            'sku' => 'CS-STYLE-LOOKUP',
            'cleaned_sku' => 'CS-STYLE-LOOKUP',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Back Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
                'Red' => '红色',
            ],
            'color_translation_resolver' => $translator,
        ]);

        $this->assertSame('红色', $this->valueForHeader($template, $row, '后背信息'));
        $this->assertSame(0, $translator->calls);
    }

    private function cachePath()
    {
        if (!is_string($this->tempDirectory)) {
            $this->tempDirectory = storage_path('app/testing/color-translation-' . uniqid());
            mkdir($this->tempDirectory, 0755, true);
        }

        return $this->tempDirectory . '/translation-cache.json';
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }

    private function deleteDirectory($directory)
    {
        foreach (array_diff(scandir($directory), ['.', '..']) as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
