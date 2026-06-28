<?php

namespace App\Http\Requests;

use App\Models\ProductProcessingCraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreOrderProcessingRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();

        return $actor
            && Gate::forUser($actor)->allows('create', ProductProcessingCraft::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'product_type_id' => $this->input('product_type_id') ?: null,
            'craft_id' => $this->input('craft_id') ?: null,
            'settlement_method' => $this->nullableTrimmed('settlement_method'),
            'spreadsheet_template' => $this->nullableTrimmed('spreadsheet_template'),
            'spreadsheet_template_description' => $this->nullableTrimmed(
                'spreadsheet_template_description'
            ),
        ]);
    }

    public function rules()
    {
        return array_merge($this->baseRules(), [
            'product_type_id' => [
                'required',
                'integer',
                Rule::exists('product_types', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
                Rule::unique('product_processing_craft', 'product_type_id'),
            ],
            'order_processor_employee_ids.*' => [
                'integer',
                'distinct',
                $this->eligibleEmployeeRule('order_processing'),
            ],
            'artwork_processor_employee_ids.*' => [
                'integer',
                'distinct',
                $this->eligibleEmployeeRule('artwork_processing'),
            ],
            'procurement_processor_employee_ids.*' => [
                'integer',
                'distinct',
                $this->eligibleEmployeeRule('procurement'),
            ],
        ]);
    }

    public function messages()
    {
        return $this->validationMessages();
    }

    protected function baseRules()
    {
        return [
            'craft_id' => [
                'nullable',
                'integer',
                Rule::exists('processing_craft_nodes', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'settlement_method' => ['nullable', 'string', 'max:255'],
            'spreadsheet_template' => ['nullable', 'string', 'max:255'],
            'spreadsheet_template_description' => ['nullable', 'string'],
            'order_processor_employee_ids' => ['nullable', 'array'],
            'artwork_processor_employee_ids' => ['nullable', 'array'],
            'procurement_processor_employee_ids' => ['nullable', 'array'],
        ];
    }

    protected function eligibleEmployeeRule($positionCode)
    {
        return Rule::exists('employees', 'id')->where(function ($query) use ($positionCode) {
            $query
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereExists(function ($positionQuery) use ($positionCode) {
                    $positionQuery
                        ->selectRaw('1')
                        ->from('employee_position')
                        ->join('positions', 'positions.id', '=', 'employee_position.position_id')
                        ->whereColumn('employee_position.employee_id', 'employees.id')
                        ->where('positions.code', $positionCode)
                        ->where('positions.is_active', true)
                        ->whereNull('positions.deleted_at');
                });
        });
    }

    protected function validationMessages()
    {
        return [
            'product_type_id.required' => '请选择产品类型。',
            'product_type_id.exists' => '所选产品类型不存在或已删除。',
            'product_type_id.unique' => '该产品类型已有订单处理配置，包括已删除的记录。',
            'craft_id.exists' => '所选工艺不存在或已删除。',
            'settlement_method.string' => '结算方式必须是文本。',
            'settlement_method.max' => '结算方式不能超过 255 个字符。',
            'spreadsheet_template.string' => '表格模板必须是文本。',
            'spreadsheet_template.max' => '表格模板不能超过 255 个字符。',
            'spreadsheet_template_description.string' => '模板说明必须是文本。',
            'order_processor_employee_ids.array' => '订单处理人格式不正确。',
            'artwork_processor_employee_ids.array' => '图画处理人格式不正确。',
            'procurement_processor_employee_ids.array' => '采购处理人格式不正确。',
            'order_processor_employee_ids.*.integer' => '订单处理人格式不正确。',
            'artwork_processor_employee_ids.*.integer' => '图画处理人格式不正确。',
            'procurement_processor_employee_ids.*.integer' => '采购处理人格式不正确。',
            'order_processor_employee_ids.*.distinct' => '订单处理人不能重复。',
            'artwork_processor_employee_ids.*.distinct' => '图画处理人不能重复。',
            'procurement_processor_employee_ids.*.distinct' => '采购处理人不能重复。',
            'order_processor_employee_ids.*.exists' => '订单处理人必须是在职的订单处理人员。',
            'artwork_processor_employee_ids.*.exists' => '图画处理人必须是在职的图画处理人员。',
            'procurement_processor_employee_ids.*.exists' => '采购处理人必须是在职的采购人员。',
        ];
    }

    private function nullableTrimmed($field)
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
