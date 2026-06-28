<?php

namespace App\Http\Requests;

use App\Models\Position;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();

        return $actor && Gate::forUser($actor)->allows('create', Position::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => trim((string) $this->input('code')),
            'permission_ids' => array_values((array) $this->input('permission_ids', [])),
        ]);
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('positions', 'code'),
            ],
            'is_active' => ['required', 'boolean'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $actor = Auth::guard('admin')->user();
            $ids = (array) $this->input('permission_ids', []);
            if (!$actor || empty($ids)) {
                return;
            }

            $canAssign = Gate::forUser($actor)->allows('assignPermissions', Position::class);
            $allowedIds = $canAssign
                ? app(PermissionService::class)->delegableFor($actor)->pluck('id')->map(function ($id) {
                    return (int) $id;
                })->all()
                : [];

            foreach ($ids as $index => $id) {
                if (!in_array((int) $id, $allowedIds, true)) {
                    $validator->errors()->add(
                        'permission_ids.'.$index,
                        '您无权分配所选权限。'
                    );
                }
            }
        });
    }

    public function messages()
    {
        return [
            'name.required' => '请输入职位名称。',
            'name.string' => '职位名称格式无效。',
            'name.max' => '职位名称不能超过 255 个字符。',
            'code.required' => '请输入职位编码。',
            'code.string' => '职位编码格式无效。',
            'code.max' => '职位编码不能超过 255 个字符。',
            'code.regex' => '职位编码只能包含小写字母、数字、点、下划线和短横线。',
            'code.unique' => '该职位编码已存在，包括已删除的记录。',
            'is_active.required' => '请选择启用状态。',
            'is_active.boolean' => '启用状态无效。',
            'permission_ids.array' => '权限选择无效。',
            'permission_ids.*.integer' => '所选权限无效。',
            'permission_ids.*.distinct' => '权限不能重复选择。',
            'permission_ids.*.exists' => '所选权限不存在。',
        ];
    }
}
