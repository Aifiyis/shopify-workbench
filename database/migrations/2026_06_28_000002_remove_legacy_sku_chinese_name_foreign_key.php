<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoveLegacySkuChineseNameForeignKey extends Migration
{
    public function up()
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            Schema::table('sku_match_product_type', function ($table) {
                $table->dropForeign('sku_match_product_type_chinese_name_foreign');
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () {
                $temporaryTable = 'sku_match_product_type_fk_rebuild';
                $columns = [
                    'id',
                    'original_sku',
                    'cleaned_sku',
                    'product_type_id',
                    'chinese_name',
                    'product_lister',
                    'product_lister_employee_id',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ];
                $rowCount = DB::table('sku_match_product_type')->count();

                DB::statement('DROP TABLE IF EXISTS "'.$temporaryTable.'"');
                DB::statement(
                    'CREATE TABLE "'.$temporaryTable.'" ('
                    .'"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, '
                    .'"original_sku" VARCHAR NOT NULL, '
                    .'"cleaned_sku" VARCHAR NOT NULL, '
                    .'"product_type_id" INTEGER NULL, '
                    .'"chinese_name" VARCHAR NOT NULL, '
                    .'"product_lister" VARCHAR NULL, '
                    .'"product_lister_employee_id" INTEGER NULL, '
                    .'"created_at" DATETIME NULL, '
                    .'"updated_at" DATETIME NULL, '
                    .'"deleted_at" DATETIME NULL'
                    .')'
                );

                $columnList = implode(', ', array_map(function ($column) {
                    return '"'.$column.'"';
                }, $columns));

                DB::statement(
                    'INSERT INTO "'.$temporaryTable.'" ('.$columnList.') '
                    .'SELECT '.$columnList.' FROM "sku_match_product_type"'
                );

                if (DB::table($temporaryTable)->count() !== $rowCount) {
                    throw new RuntimeException('SKU mapping row count changed during foreign key removal.');
                }

                DB::statement('DROP TABLE "sku_match_product_type"');
                DB::statement(
                    'ALTER TABLE "'.$temporaryTable.'" RENAME TO "sku_match_product_type"'
                );

                DB::statement(
                    'CREATE UNIQUE INDEX "sku_match_product_type_original_sku_unique" '
                    .'ON "sku_match_product_type" ("original_sku")'
                );
                DB::statement(
                    'CREATE INDEX "sku_match_product_type_cleaned_sku_index" '
                    .'ON "sku_match_product_type" ("cleaned_sku")'
                );
                DB::statement(
                    'CREATE INDEX "sku_match_product_type_product_type_id_index" '
                    .'ON "sku_match_product_type" ("product_type_id")'
                );
                DB::statement(
                    'CREATE INDEX "sku_match_product_type_chinese_name_index" '
                    .'ON "sku_match_product_type" ("chinese_name")'
                );
                DB::statement(
                    'CREATE INDEX "sku_match_product_type_product_lister_employee_id_index" '
                    .'ON "sku_match_product_type" ("product_lister_employee_id")'
                );
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down()
    {
        // Intentionally irreversible: restoring the obsolete FK could reject valid independent product types.
    }
}
