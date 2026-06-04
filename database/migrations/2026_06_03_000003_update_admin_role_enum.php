<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateAdminRoleEnum extends Migration
{
    public function up()
    {
        // For SQLite, we need to drop and recreate the constraint
        // Since SQLite doesn't support ALTER COLUMN directly for CHECK constraints,
        // we'll use raw SQL to recreate the table with the updated constraint

        if (DB::connection()->getDriverName() === 'sqlite') {
            // Get all the data first
            $admins = DB::table('admins')->get();

            // Drop the old table
            DB::statement('DROP TABLE admins');

            // Recreate with the new constraint
            DB::statement(<<<'SQL'
                CREATE TABLE "admins" (
                    "id" integer not null primary key autoincrement,
                    "name" varchar not null,
                    "email" varchar not null unique,
                    "password" varchar not null,
                    "role" varchar check ("role" in ('super', 'manager', 'employee')) not null default 'manager',
                    "is_active" tinyint(1) not null default '1',
                    "last_login" datetime,
                    "remember_token" varchar,
                    "created_at" datetime,
                    "updated_at" datetime,
                    "parent_admin_id" integer,
                    "company_name" varchar,
                    "is_manageable" tinyint(1) not null default '1',
                    foreign key("parent_admin_id") references "admins"("id") on delete set null
                )
            SQL);

            // Reinsert the data
            foreach ($admins as $admin) {
                DB::table('admins')->insert((array) $admin);
            }
        }
    }

    public function down()
    {
        // Not implementing down() for SQLite table recreation
        // as it's complex and not typically needed for rollback
    }
}
