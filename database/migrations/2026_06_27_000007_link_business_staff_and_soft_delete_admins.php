<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkBusinessStaffAndSoftDeleteAdmins extends Migration
{
    public function up()
    {
        Schema::table('sku_match_product_type', function (Blueprint $table) {
            $table->unsignedBigInteger('product_lister_employee_id')->nullable()->index();
        });

        Schema::table('product_processing_craft', function (Blueprint $table) {
            $table->unsignedBigInteger('order_processor_employee_id')->nullable()->index();
            $table->unsignedBigInteger('artwork_processor_employee_id')->nullable()->index();
            $table->unsignedBigInteger('procurement_processor_employee_id')->nullable()->index();
            $table->string('settlement_method')->nullable();
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('product_processing_craft', function (Blueprint $table) {
            $table->dropColumn([
                'order_processor_employee_id',
                'artwork_processor_employee_id',
                'procurement_processor_employee_id',
                'settlement_method',
            ]);
        });

        Schema::table('sku_match_product_type', function (Blueprint $table) {
            $table->dropColumn('product_lister_employee_id');
        });
    }
}
