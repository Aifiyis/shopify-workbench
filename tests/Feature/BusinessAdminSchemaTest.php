<?php

namespace Tests\Feature;

use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessAdminSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_types_link_business_tables_with_soft_deletes()
    {
        $productType = ProductType::create([
            'chinese_name' => '彩图刺绣',
        ]);

        $processingCraft = ProductProcessingCraft::create([
            'chinese_name' => '彩图刺绣',
            'product_type_id' => $productType->id,
        ]);

        $skuMatch = SkuMatchProductType::create([
            'original_sku' => 'RAW-1',
            'cleaned_sku' => 'CLEAN-1',
            'chinese_name' => '彩图刺绣',
            'product_type_id' => $productType->id,
        ]);

        $this->assertTrue(Schema::hasColumn('sku_match_product_type', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('product_processing_craft', 'deleted_at'));
        $this->assertTrue($productType->is($skuMatch->productType));
        $this->assertTrue($productType->is($processingCraft->productType));
    }
}
