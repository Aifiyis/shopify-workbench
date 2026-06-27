<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToBusinessTables extends Migration
{
    public function up()
    {
        Schema::table('sku_match_product_type', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('product_processing_craft', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('processing_craft_nodes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('processing_craft_nodes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('product_processing_craft', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sku_match_product_type', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
