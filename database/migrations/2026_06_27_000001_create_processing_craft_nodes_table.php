<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcessingCraftNodesTable extends Migration
{
    public function up()
    {
        Schema::create('processing_craft_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('上级工艺');
            $table->string('name')->comment('工艺节点名称');
            $table->string('path')->unique()->comment('完整工艺路径');
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('processing_craft_nodes')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('processing_craft_nodes');
    }
}
