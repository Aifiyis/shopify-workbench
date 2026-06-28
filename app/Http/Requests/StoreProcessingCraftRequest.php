<?php

namespace App\Http\Requests;

use App\Models\ProcessingCraftNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProcessingCraftRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();

        return $actor
            && Gate::forUser($actor)->allows('create', ProcessingCraftNode::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function rules()
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('processing_craft_nodes', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'name' => ['required', 'string', 'max:255', 'not_regex:/-/u'],
        ];
    }

    public function withValidator($validator)
    {
        $this->validateCraft($validator);
    }

    public function messages()
    {
        return [
            'parent_id.exists' => '所选上级工艺不存在或已删除。',
            'name.required' => '请输入工艺名称。',
            'name.max' => '工艺名称不能超过 255 个字符。',
            'name.not_regex' => '工艺名称不能包含连字符（-）。',
        ];
    }

    protected function validateCraft($validator, $ignoreId = null, $checkCycle = false)
    {
        $validator->after(function ($validator) use ($ignoreId, $checkCycle) {
            if ($validator->errors()->has('parent_id') || $validator->errors()->has('name')) {
                return;
            }

            $parentId = $this->input('parent_id');
            if ($checkCycle && $this->createsCycle($parentId, $ignoreId)) {
                $validator->errors()->add('parent_id', '上级工艺不能选择当前工艺或其下级工艺。');

                return;
            }

            $parent = $parentId
                ? ProcessingCraftNode::query()->find($parentId)
                : null;
            $path = $parent
                ? $parent->path.'-'.$this->input('name')
                : $this->input('name');

            if (mb_strlen($path) > 255) {
                $validator->errors()->add('name', '完整工艺路径不能超过 255 个字符。');

                return;
            }

            $duplicate = ProcessingCraftNode::withTrashed()
                ->where('path', $path)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('name', '该工艺已存在，包括已删除的记录。');
            }
        });
    }

    private function createsCycle($parentId, $craftId)
    {
        if (!$parentId || !$craftId) {
            return false;
        }

        $candidateId = (int) $parentId;
        while ($candidateId) {
            if ($candidateId === (int) $craftId) {
                return true;
            }

            $candidateId = (int) ProcessingCraftNode::withTrashed()
                ->where('id', $candidateId)
                ->value('parent_id');
        }

        return false;
    }
}
