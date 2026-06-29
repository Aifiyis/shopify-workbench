<?php

namespace App\Http\Requests;

use App\Models\SkuMatchProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkUpdateSkuMatchProductTypeRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();

        return $actor
            && Gate::forUser($actor)->allows('create', SkuMatchProductType::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'product_type_id' => $this->input('product_type_id') ?: null,
            'product_lister_employee_id' => $this->input('product_lister_employee_id') ?: null,
        ]);
    }

    public function rules()
    {
        return [
            'sku_ids' => ['required', 'array', 'min:2', 'max:100'],
            'sku_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('sku_match_product_type', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'product_type_id' => [
                'required',
                'integer',
                Rule::exists('product_types', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'product_lister_employee_id' => [
                'nullable',
                'integer',
                $this->eligibleListerRule(),
            ],
            'return_query' => ['nullable', 'array'],
            'return_query.tab' => ['nullable', Rule::in(['skus'])],
            'return_query.search' => ['nullable', 'string', 'max:255'],
            'return_query.product_type_id' => ['nullable', 'integer'],
            'return_query.sku_page' => ['nullable', 'integer', 'min:1'],
            'return_query.sku_per_page' => ['nullable', Rule::in([20, 50, 100])],
        ];
    }

    public function messages()
    {
        return [
            'sku_ids.required' => '请至少选择两条 SKU 映射。',
            'sku_ids.min' => '请至少选择两条 SKU 映射。',
            'sku_ids.max' => '每次最多批量修改 100 条 SKU 映射。',
            'sku_ids.*.distinct' => 'SKU 映射不能重复选择。',
            'sku_ids.*.exists' => '所选 SKU 映射不存在或已删除。',
            'product_type_id.required' => '请选择产品类型。',
            'product_type_id.exists' => '所选产品类型不存在或已删除。',
            'product_lister_employee_id.exists' => '所选上品人必须是在职的广告或运营人员。',
        ];
    }

    private function eligibleListerRule()
    {
        return Rule::exists('employees', 'id')->where(function ($query) {
            $query
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereExists(function ($positionQuery) {
                    $positionQuery
                        ->selectRaw('1')
                        ->from('employee_position')
                        ->join('positions', 'positions.id', '=', 'employee_position.position_id')
                        ->whereColumn('employee_position.employee_id', 'employees.id')
                        ->whereIn('positions.code', ['advertising', 'operations'])
                        ->where('positions.is_active', true)
                        ->whereNull('positions.deleted_at');
                });
        });
    }
}
