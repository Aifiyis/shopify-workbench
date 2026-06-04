<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendAdminsTable extends Migration
{
    public function up()
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_admin_id')->nullable()->after('role');
            $table->string('company_name')->nullable()->after('parent_admin_id');
            $table->boolean('is_manageable')->default(true)->after('company_name');

            $table->foreign('parent_admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropForeign(['parent_admin_id']);
            $table->dropColumn(['parent_admin_id', 'company_name', 'is_manageable']);
        });
    }
}
