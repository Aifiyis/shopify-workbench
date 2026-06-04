<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderLineItemsTable extends Migration
{
    public function up()
    {
        Schema::create('order_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('shopify_line_item_id')->unique();
            $table->string('product_title')->nullable();
            $table->string('product_type')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('option1')->nullable();
            $table->string('option3')->nullable();
            $table->string('product_tags')->nullable();
            $table->string('sku')->nullable();

            // 转换后的字段
            $table->text('multi_types')->nullable();
            $table->text('picture_url')->nullable();
            $table->string('pic_name')->nullable();
            $table->text('extra_details')->nullable();
            $table->text('custom_text')->nullable();

            // 原始数据备份
            $table->json('raw_properties')->nullable();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index('order_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_line_items');
    }
}
