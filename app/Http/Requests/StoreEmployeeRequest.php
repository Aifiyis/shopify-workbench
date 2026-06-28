<?php

namespace App\Http\Requests;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();

        return $actor && Gate::forUser($actor)->allows('create', Employee::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'company_name' => $this->nullableTrimmed('company_name'),
            'supervisor_id' => $this->input('supervisor_id') ?: null,
            'admin_id' => $this->input('admin_id') ?: null,
            'position_ids' => array_values((array) $this->input('position_ids', [])),
        ]);
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'supervisor_id' => array_merge(
                ['nullable', 'integer'],
                [$this->supervisorRule()]
            ),
            'admin_id' => ['nullable', 'integer', $this->adminRule()],
            'is_active' => ['required', 'boolean'],
            'position_ids' => ['array'],
            'position_ids.*' => ['integer', 'distinct', $this->positionRule()],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '请输入员工姓名。',
            'name.string' => '员工姓名格式无效。',
            'name.max' => '员工姓名不能超过 255 个字符。',
            'company_name.string' => '公司名称格式无效。',
            'company_name.max' => '公司名称不能超过 255 个字符。',
            'supervisor_id.integer' => '所选上级无效。',
            'admin_id.integer' => '所选账号无效。',
            'is_active.required' => '请选择在职状态。',
            'is_active.boolean' => '在职状态无效。',
            'position_ids.array' => '职位选择无效。',
            'position_ids.*.integer' => '所选职位无效。',
            'position_ids.*.distinct' => '职位不能重复选择。',
        ];
    }

    protected function currentEmployee()
    {
        return null;
    }

    private function nullableTrimmed($key)
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function supervisorRule()
    {
        return function ($attribute, $value, $fail) {
            if ($value === null) {
                return;
            }

            $actor = Auth::guard('admin')->user();
            $current = $this->currentEmployee();

            if ($current && (int) $value === (int) $current->id) {
                $fail('员工不能将自己设为上级。');
                return;
            }

            if ($actor && $actor->role === 'manager') {
                $managerEmployeeId = $actor->employee()
                    ->where('is_active', true)
                    ->value('id');

                if (!$managerEmployeeId || (int) $value !== (int) $managerEmployeeId) {
                    $fail('管理员只能将自己设为直属员工的上级。');
                }
                return;
            }

            $isCurrent = $current && (int) $value === (int) $current->supervisor_id;
            $exists = Employee::query()
                ->whereKey($value)
                ->where('is_active', true)
                ->exists();

            if (!$isCurrent && !$exists) {
                $fail('所选上级不存在或已离职。');
            }
        };
    }

    private function adminRule()
    {
        return function ($attribute, $value, $fail) {
            if ($value === null) {
                return;
            }

            $current = $this->currentEmployee();
            if ($current && (int) $value === (int) $current->admin_id) {
                return;
            }

            $eligible = Admin::query()
                ->whereKey($value)
                ->where('is_active', true)
                ->whereNotExists(function ($query) {
                    $query
                        ->select(DB::raw(1))
                        ->from('employees')
                        ->whereColumn('employees.admin_id', 'admins.id');
                })
                ->exists();

            if (!$eligible) {
                $fail('所选账号不存在、已停用或已关联其他员工。');
            }
        };
    }

    private function positionRule()
    {
        return function ($attribute, $value, $fail) {
            $current = $this->currentEmployee();
            $existingIds = $current
                ? DB::table('employee_position')
                    ->where('employee_id', $current->id)
                    ->pluck('position_id')
                    ->map(function ($id) {
                        return (int) $id;
                    })
                    ->all()
                : [];

            if (in_array((int) $value, $existingIds, true)) {
                return;
            }

            $eligible = Position::query()
                ->whereKey($value)
                ->where('is_active', true)
                ->exists();

            if (!$eligible) {
                $fail('新分配的职位必须处于启用状态且未被删除。');
            }
        };
    }
}
