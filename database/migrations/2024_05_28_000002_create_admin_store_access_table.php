<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminStoreAccessTable extends Migration
{
    public function up()
    {
        Schema::create('admin_store_access', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('store_id');
            $table->enum('access_level', ['view', 'edit'])->default('view');
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('shopify_stores')->onDelete('cascade');
            $table->unique(['admin_id', 'store_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_store_access');
    }
}
