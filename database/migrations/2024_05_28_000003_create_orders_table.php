<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('shopify_order_id')->unique();
            $table->dateTime('order_date')->nullable();
            $table->string('order_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->integer('line_items_count')->default(0);
            $table->timestamp('cached_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('shopify_stores')->onDelete('cascade');
            $table->index('store_id');
            $table->index('order_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
