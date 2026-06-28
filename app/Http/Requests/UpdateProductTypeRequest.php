<?php

namespace App\Http\Requests;

use App\Models\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProductTypeRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();
        $productType = $this->route('product_type');

        return $actor
            && $productType instanceof ProductType
            && Gate::forUser($actor)->allows('update', $productType);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'chinese_name' => trim((string) $this->input('chinese_name')),
        ]);
    }

    public function rules()
    {
        $productType = $this->route('product_type');

        return [
            'chinese_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_types', 'chinese_name')->ignore($productType->id),
            ],
        ];
    }

    public function messages()
    {
        return [
            'chinese_name.required' => '请输入产品类型名称。',
            'chinese_name.unique' => '该产品类型已存在，包括已删除的记录。',
        ];
    }
}
