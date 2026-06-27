<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkuMatchProductTypeTable extends Migration
{
    public function up()
    {
        Schema::create('sku_match_product_type', function (Blueprint $table) {
            $table->id();
            $table->string('original_sku')->unique()->comment('原始SKU');
            $table->string('cleaned_sku')->index()->comment('清洗后的SKU');
            $table->string('chinese_name')->index()->comment('中文名称');
            $table->string('product_lister')->nullable()->comment('上品人');
            $table->timestamps();

            $table->foreign('chinese_name')
                ->references('chinese_name')
                ->on('product_processing_craft')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sku_match_product_type');
    }
}
