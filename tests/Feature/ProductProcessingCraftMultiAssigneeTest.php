<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Position;
use App\Models\ProductProcessingCraft;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductProcessingCraftMultiAssigneeTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_relations_support_equal_rank_and_independent_assignments()
    {
        $orderPosition = Position::create([
            'name' => 'Order processing',
            'code' => 'order_processing',
        ]);
        $artworkPosition = Position::create([
            'name' => 'Artwork processing',
            'code' => 'artwork_processing',
        ]);
        $procurementPosition = Position::create([
            'name' => 'Procurement',
            'code' => 'procurement',
        ]);

        $orderEmployeeA = Employee::create(['name' => 'Order A']);
        $orderEmployeeB = Employee::create(['name' => 'Order B']);
        $procurementEmployee = Employee::create(['name' => 'Procurement A']);

        $orderEmployeeA->positions()->attach([$orderPosition->id, $artworkPosition->id]);
        $orderEmployeeB->positions()->attach($orderPosition->id);
        $procurementEmployee->positions()->attach($procurementPosition->id);

        $craft = ProductProcessingCraft::create([
            'chinese_name' => 'Multi assignee product',
            'order_processor' => 'Legacy order',
            'artwork_processor' => 'Legacy artwork',
            'procurement_processor' => 'Legacy procurement',
            'order_processor_employee_id' => $orderEmployeeA->id,
            'artwork_processor_employee_id' => $orderEmployeeB->id,
            'procurement_processor_employee_id' => $procurementEmployee->id,
        ]);

        $craft->orderProcessorEmployees()->sync([
            $orderEmployeeA->id => ['assignment_type' => 'order_processing'],
            $orderEmployeeB->id => ['assignment_type' => 'order_processing'],
        ]);
        $craft->artworkProcessorEmployees()->sync([
            $orderEmployeeA->id => ['assignment_type' => 'artwork_processing'],
        ]);
        $craft->procurementProcessorEmployees()->sync([
            $procurementEmployee->id => ['assignment_type' => 'procurement'],
        ]);

        $freshCraft = $craft->fresh();

        $this->assertEqualsCanonicalizing(
            [$orderEmployeeA->id, $orderEmployeeB->id],
            $freshCraft->orderProcessorEmployees->pluck('id')->all()
        );
        $this->assertSame(
            [$orderEmployeeA->id],
            $freshCraft->artworkProcessorEmployees->pluck('id')->all()
        );
        $this->assertSame(
            [$procurementEmployee->id],
            $freshCraft->procurementProcessorEmployees->pluck('id')->all()
        );
        $this->assertSame(4, DB::table(
            'product_processing_craft_employee_assignment'
        )->count());

        $this->assertSame('Legacy order', $freshCraft->order_processor);
        $this->assertSame('Legacy artwork', $freshCraft->artwork_processor);
        $this->assertSame('Legacy procurement', $freshCraft->procurement_processor);
        $this->assertEquals($orderEmployeeA->id, $freshCraft->order_processor_employee_id);
        $this->assertEquals($orderEmployeeB->id, $freshCraft->artwork_processor_employee_id);
        $this->assertEquals(
            $procurementEmployee->id,
            $freshCraft->procurement_processor_employee_id
        );
    }

    public function test_unique_triple_rejects_an_exact_duplicate()
    {
        $employee = Employee::create(['name' => 'Duplicate employee']);
        $craft = ProductProcessingCraft::create(['chinese_name' => 'Duplicate product']);
        $timestamp = now();
        $assignment = [
            'product_processing_craft_id' => $craft->id,
            'employee_id' => $employee->id,
            'assignment_type' => 'order_processing',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        DB::table('product_processing_craft_employee_assignment')->insert($assignment);

        $this->expectException(QueryException::class);
        DB::table('product_processing_craft_employee_assignment')->insert($assignment);
    }

    public function test_soft_deleted_history_remains_visible_from_both_relations()
    {
        $employee = Employee::create(['name' => 'Historical employee']);
        $craft = ProductProcessingCraft::create(['chinese_name' => 'Historical product']);

        $craft->orderProcessorEmployees()->attach($employee->id, [
            'assignment_type' => 'order_processing',
        ]);

        $employee->delete();

        $this->assertTrue(
            $employee->is($craft->fresh()->orderProcessorEmployees->first())
        );

        $craft->delete();
        $historicalEmployee = Employee::withTrashed()->findOrFail($employee->id);

        $this->assertTrue(
            $craft->is($historicalEmployee->processingCraftAssignments->first())
        );
    }

    public function test_migration_backfills_existing_single_employee_columns_only()
    {
        $employeeA = Employee::create(['name' => 'Existing employee A']);
        $employeeB = Employee::create(['name' => 'Existing employee B']);
        $craft = ProductProcessingCraft::create([
            'chinese_name' => 'Existing product',
            'order_processor' => 'Old order text',
            'artwork_processor' => 'Old artwork text',
            'procurement_processor' => 'Old procurement text',
            'order_processor_employee_id' => $employeeA->id,
            'artwork_processor_employee_id' => $employeeA->id,
            'procurement_processor_employee_id' => $employeeB->id,
        ]);
        $sourceRow = DB::table('product_processing_craft')->find($craft->id);

        require_once database_path(
            'migrations/2026_06_28_000001_create_product_processing_craft_employee_assignment_table.php'
        );
        $migration = new \CreateProductProcessingCraftEmployeeAssignmentTable();
        $migration->down();

        $this->assertTrue(Schema::hasTable('product_processing_craft'));

        $migration->up();

        $this->assertSame(3, DB::table(
            'product_processing_craft_employee_assignment'
        )->where('product_processing_craft_id', $craft->id)->count());
        $this->assertDatabaseHas('product_processing_craft_employee_assignment', [
            'product_processing_craft_id' => $craft->id,
            'employee_id' => $employeeA->id,
            'assignment_type' => 'order_processing',
        ]);
        $this->assertDatabaseHas('product_processing_craft_employee_assignment', [
            'product_processing_craft_id' => $craft->id,
            'employee_id' => $employeeA->id,
            'assignment_type' => 'artwork_processing',
        ]);
        $this->assertDatabaseHas('product_processing_craft_employee_assignment', [
            'product_processing_craft_id' => $craft->id,
            'employee_id' => $employeeB->id,
            'assignment_type' => 'procurement',
        ]);
        $this->assertEquals(
            $sourceRow,
            DB::table('product_processing_craft')->find($craft->id)
        );
    }
}
