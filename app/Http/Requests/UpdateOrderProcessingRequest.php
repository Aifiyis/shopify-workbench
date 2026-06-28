<?php

namespace App\Http\Requests;

use App\Models\ProductProcessingCraft;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateOrderProcessingRequest extends StoreOrderProcessingRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();
        $configuration = $this->route('order_processing');

        return $actor
            && $configuration instanceof ProductProcessingCraft
            && Gate::forUser($actor)->allows('update', $configuration);
    }

    public function rules()
    {
        $configuration = $this->route('order_processing');

        return array_merge($this->baseRules(), [
            'product_type_id' => [
                'required',
                'integer',
                Rule::exists('product_types', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
                Rule::unique('product_processing_craft', 'product_type_id')
                    ->ignore($configuration->id),
            ],
            'order_processor_employee_ids.*' => [
                'integer',
                'distinct',
                $this->eligibleOrHistoricalRule('order_processing'),
            ],
            'artwork_processor_employee_ids.*' => [
                'integer',
                'distinct',
                $this->eligibleOrHistoricalRule('artwork_processing'),
            ],
            'procurement_processor_employee_ids.*' => [
                'integer',
                'distinct',
                $this->eligibleOrHistoricalRule('procurement'),
            ],
        ]);
    }

    private function eligibleOrHistoricalRule($assignmentType)
    {
        return function ($attribute, $value, $fail) use ($assignmentType) {
            $eligible = DB::table('employees')
                ->where('employees.id', $value)
                ->where('employees.is_active', true)
                ->whereNull('employees.deleted_at')
                ->whereExists(function ($query) use ($assignmentType) {
                    $query
                        ->selectRaw('1')
                        ->from('employee_position')
                        ->join('positions', 'positions.id', '=', 'employee_position.position_id')
                        ->whereColumn('employee_position.employee_id', 'employees.id')
                        ->where('positions.code', $assignmentType)
                        ->where('positions.is_active', true)
                        ->whereNull('positions.deleted_at');
                })
                ->exists();

            if ($eligible) {
                return;
            }

            $configuration = $this->route('order_processing');
            $historicalAssignment = DB::table('employees')
                ->join(
                    'product_processing_craft_employee_assignment as assignments',
                    'assignments.employee_id',
                    '=',
                    'employees.id'
                )
                ->where('assignments.product_processing_craft_id', $configuration->id)
                ->where('assignments.assignment_type', $assignmentType)
                ->where('assignments.employee_id', $value)
                ->exists();

            if (!$historicalAssignment) {
                $fail($this->employeeErrorMessage($assignmentType));
            }
        };
    }

    private function employeeErrorMessage($assignmentType)
    {
        return [
            'order_processing' => '订单处理人必须是在职的订单处理人员。',
            'artwork_processing' => '图画处理人必须是在职的图画处理人员。',
            'procurement' => '采购处理人必须是在职的采购人员。',
        ][$assignmentType];
    }
}
