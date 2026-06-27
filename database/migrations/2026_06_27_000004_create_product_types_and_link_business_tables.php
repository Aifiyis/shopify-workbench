<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductTypesAndLinkBusinessTables extends Migration
{
    public function up()
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('chinese_name')->unique()->comment('中文名称');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('sku_match_product_type', function (Blueprint $table) {
            $table->unsignedBigInteger('product_type_id')->nullable()->index()->after('cleaned_sku');
        });

        Schema::table('product_processing_craft', function (Blueprint $table) {
            $table->unsignedBigInteger('product_type_id')->nullable()->unique()->after('chinese_name');
        });
    }

    public function down()
    {
        Schema::table('product_processing_craft', function (Blueprint $table) {
            $table->dropColumn('product_type_id');
        });

        Schema::table('sku_match_product_type', function (Blueprint $table) {
            $table->dropColumn('product_type_id');
        });

        Schema::dropIfExists('product_types');
    }
}
