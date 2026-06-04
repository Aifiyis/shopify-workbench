<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcessedFilesTable extends Migration
{
    public function up()
    {
        Schema::create('processed_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('original_filename');
            $table->string('processed_filename')->unique();
            $table->string('file_path');
            $table->string('status')->default('completed'); // processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('expires_at');
            $table->boolean('is_downloaded')->default(false);
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
            $table->index('expires_at');
            $table->index('admin_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('processed_files');
    }
}
