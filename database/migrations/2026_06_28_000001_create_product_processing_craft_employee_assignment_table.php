<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductProcessingCraftEmployeeAssignmentTable extends Migration
{
    public function up()
    {
        Schema::create('product_processing_craft_employee_assignment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_processing_craft_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('assignment_type', 40);
            $table->timestamps();

            $table->unique(
                ['product_processing_craft_id', 'employee_id', 'assignment_type'],
                'ppc_employee_assignment_unique'
            );
            $table->index(
                ['assignment_type', 'product_processing_craft_id'],
                'ppc_assignment_type_lookup'
            );
            $table->index(
                ['employee_id', 'assignment_type'],
                'ppc_employee_type_lookup'
            );

            $table->foreign('product_processing_craft_id', 'ppc_assignment_craft_fk')
                ->references('id')
                ->on('product_processing_craft')
                ->onDelete('cascade');
            $table->foreign('employee_id', 'ppc_assignment_employee_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('restrict');
        });

        $timestamp = now();

        DB::table('product_processing_craft')
            ->select([
                'id',
                'order_processor_employee_id',
                'artwork_processor_employee_id',
                'procurement_processor_employee_id',
            ])
            ->chunkById(100, function ($processingCrafts) use ($timestamp) {
                $assignments = [];

                foreach ($processingCrafts as $processingCraft) {
                    foreach ([
                        'order_processor_employee_id' => 'order_processing',
                        'artwork_processor_employee_id' => 'artwork_processing',
                        'procurement_processor_employee_id' => 'procurement',
                    ] as $employeeColumn => $assignmentType) {
                        if ($processingCraft->{$employeeColumn} === null) {
                            continue;
                        }

                        $assignments[] = [
                            'product_processing_craft_id' => $processingCraft->id,
                            'employee_id' => $processingCraft->{$employeeColumn},
                            'assignment_type' => $assignmentType,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }

                if ($assignments) {
                    DB::table('product_processing_craft_employee_assignment')
                        ->insertOrIgnore($assignments);
                }
            });
    }

    public function down()
    {
        Schema::dropIfExists('product_processing_craft_employee_assignment');
    }
}
