<?php

namespace App\Http\Requests;

use App\Models\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProductTypeRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();

        return $actor && Gate::forUser($actor)->allows('create', ProductType::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'chinese_name' => trim((string) $this->input('chinese_name')),
        ]);
    }

    public function rules()
    {
        $rules = ['required', 'string', 'max:255'];

        if (!$this->route() || $this->route()->getName() !== 'product-types.quick-store') {
            $rules[] = Rule::unique('product_types', 'chinese_name');
        }

        return ['chinese_name' => $rules];
    }

    public function messages()
    {
        return [
            'chinese_name.required' => '请输入产品类型名称。',
            'chinese_name.unique' => '该产品类型已存在，包括已删除的记录。',
        ];
    }
}
