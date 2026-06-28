<?php

namespace App\Http\Requests;

use App\Models\SkuMatchProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSkuMatchProductTypeRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();
        $skuMatch = $this->route('sku_product_type');

        return $actor
            && $skuMatch instanceof SkuMatchProductType
            && Gate::forUser($actor)->allows('update', $skuMatch);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'original_sku' => trim((string) $this->input('original_sku')),
            'cleaned_sku' => trim((string) $this->input('cleaned_sku')),
            'product_type_id' => $this->input('product_type_id') ?: null,
            'product_lister_employee_id' => $this->input('product_lister_employee_id') ?: null,
        ]);
    }

    public function rules()
    {
        $skuMatch = $this->route('sku_product_type');

        return [
            'original_sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sku_match_product_type', 'original_sku')->ignore($skuMatch->id),
            ],
            'cleaned_sku' => ['required', 'string', 'max:255'],
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
        ];
    }

    public function messages()
    {
        return [
            'original_sku.required' => '请输入原始 SKU。',
            'original_sku.unique' => '该原始 SKU 已存在，包括已删除的记录。',
            'cleaned_sku.required' => '请输入清洗后 SKU。',
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
