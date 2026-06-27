<?php

namespace Tests\Unit;

use App\Services\SkuMatchProductTypeImportService;
use Database\Seeders\SkuMatchProductTypeSeeder;
use Mockery;
use Tests\TestCase;

class SkuMatchProductTypeSeederTest extends TestCase
{
    public function test_seeder_delegates_to_import_service()
    {
        $expected = [
            'sku_count' => 3928,
            'processing_config_count' => 318,
            'craft_node_count' => 64,
            'matched_sku_count' => 3770,
            'unmatched_sku_count' => 158,
        ];
        $service = Mockery::mock(SkuMatchProductTypeImportService::class);
        $service->shouldReceive('import')->once()->andReturn($expected);

        $seeder = new SkuMatchProductTypeSeeder($service);

        $this->assertSame($expected, $seeder->run());
    }
}
