@extends('layouts.app')

@section('content')
    @php
        $editing = $admin->exists;
        $action = $editing ? route('admins.update', $admin) : route('admins.store');
        $roleLabels = [
            'super' => '超级管理员',
            'manager' => '管理员',
            'employee' => '员工',
        ];
        $selectedStoreIds = array_map('intval', (array) old('store_ids', $assignedStores));
        $selectedAccessLevels = (array) old('access_levels', $assignedAccessLevels);
        $selectedEmployeeId = old('employee_id', $currentEmployeeId);
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">{{ $editing ? '编辑管理员账号' : '新增管理员账号' }}</h1>
        <a class="button button-secondary no-underline" href="{{ route('admins.index') }}">返回列表</a>
    </div>

    <form method="POST" action="{{ $action }}" class="mt-5 max-w-4xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="name" class="mb-1 block font-semibold">姓名</label>
                <input id="name" name="name" required maxlength="255"
                       value="{{ old('name', $admin->name) }}">
            </div>
            <div>
                <label for="email" class="mb-1 block font-semibold">邮箱</label>
                <input id="email" name="email" type="email" required maxlength="255"
                       value="{{ old('email', $admin->email) }}">
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="password" class="mb-1 block font-semibold">
                    密码{{ $editing ? '（留空则不修改）' : '' }}
                </label>
                <input id="password" name="password" type="password"
                       minlength="8" @if (!$editing) required @endif autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block font-semibold">确认密码</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       minlength="8" @if (!$editing) required @endif autocomplete="new-password">
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="role" class="mb-1 block font-semibold">角色</label>
                <select id="role" name="role" required>
                    @foreach ($availableRoles as $role)
                        <option value="{{ $role }}"
                                @if (old('role', $admin->role ?: 'employee') === $role) selected @endif>
                            {{ $roleLabels[$role] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="company_name" class="mb-1 block font-semibold">公司名称</label>
                <input id="company_name" name="company_name" maxlength="255"
                       value="{{ old('company_name', $admin->company_name) }}">
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="employee_id" class="mb-1 block font-semibold">关联员工档案</label>
                <select id="employee_id" name="employee_id" data-searchable-select
                        data-placeholder="不关联员工档案">
                    <option value="">不关联员工档案</option>
                    @foreach ($employeeOptions as $employee)
                        <option value="{{ $employee->id }}"
                                @if ((string) $selectedEmployeeId === (string) $employee->id) selected @endif>
                            {{ $employee->name }}{{ $employee->company_name ? ' · '.$employee->company_name : '' }}{{ $employee->trashed() ? '（已删除，仅可保留）' : (!$employee->is_active ? '（已停用，仅可保留）' : '') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end pb-2">
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 font-semibold">
                    <input class="h-4 w-4" type="checkbox" name="is_active" value="1"
                           @if ((bool) old('is_active', $admin->is_active ?? true)) checked @endif>
                    启用账号
                </label>
            </div>
        </div>

        <fieldset class="border-t border-gray-200 pt-5">
            <legend class="pr-3 font-semibold">Shopify 店铺访问</legend>
            <div class="mt-2 overflow-x-auto border border-gray-200 bg-white text-sm">
                <table>
                    <thead>
                        <tr>
                            <th class="w-20">选择</th>
                            <th>店铺</th>
                            <th class="w-36">访问权限</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                            @php
                                $selected = in_array((int) $store->id, $selectedStoreIds, true);
                                $accessLevel = $selectedAccessLevels[$store->id] ?? 'view';
                            @endphp
                            <tr>
                                <td>
                                    <input class="store-checkbox h-4 w-4" type="checkbox"
                                           id="store-{{ $store->id }}" name="store_ids[]"
                                           value="{{ $store->id }}" @if ($selected) checked @endif>
                                </td>
                                <td>
                                    <label for="store-{{ $store->id }}" class="cursor-pointer">
                                        {{ $store->shop_name }}
                                        @if (!$store->is_active)
                                            <span class="text-xs text-gray-500">（已停用，仅可保留）</span>
                                        @endif
                                    </label>
                                </td>
                                <td>
                                    <select name="access_levels[{{ $store->id }}]" class="store-access-level">
                                        <option value="view" @if ($accessLevel === 'view') selected @endif>查看</option>
                                        <option value="edit" @if ($accessLevel === 'edit') selected @endif>编辑</option>
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-500">暂无可用店铺。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </fieldset>

        <div class="flex gap-2 border-t border-gray-200 pt-4">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">
                {{ $editing ? '保存修改' : '创建账号' }}
            </button>
            <a class="button button-secondary no-underline" href="{{ route('admins.index') }}">取消</a>
        </div>
    </form>

    <script>
        document.querySelectorAll('.store-checkbox').forEach(function (checkbox) {
            const accessLevel = checkbox.closest('tr').querySelector('.store-access-level');
            const updateState = function () {
                accessLevel.disabled = !checkbox.checked;
            };

            checkbox.addEventListener('change', updateState);
            updateState();
        });
    </script>
@endsection
