<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductProcessingCraftTable extends Migration
{
    public function up()
    {
        Schema::create('product_processing_craft', function (Blueprint $table) {
            $table->id();
            $table->string('chinese_name')->unique()->comment('中文名称');
            $table->unsignedBigInteger('craft_id')->nullable()->comment('工艺');
            $table->string('order_processor')->nullable()->comment('订单处理人');
            $table->string('artwork_processor')->nullable()->comment('图画处理人');
            $table->string('procurement_processor')->nullable()->comment('采购处理人');
            $table->string('spreadsheet_template')->nullable()->comment('表格模板');
            $table->text('spreadsheet_template_description')->nullable()->comment('表格模板说明');
            $table->timestamps();

            $table->foreign('craft_id')
                ->references('id')
                ->on('processing_craft_nodes')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_processing_craft');
    }
}
