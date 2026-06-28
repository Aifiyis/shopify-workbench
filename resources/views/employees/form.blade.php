@extends('layouts.app')

@section('content')
    @php
        $editing = $employee->exists;
        $action = $editing ? route('employees.update', $employee) : route('employees.store');
        $selectedPositions = array_map('intval', (array) old('position_ids', $assignedPositionIds));
        $companyValue = old('company_name', $employee->company_name);
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">{{ $editing ? '编辑员工档案' : '新增员工档案' }}</h1>
        <a class="button button-secondary no-underline" href="{{ route('employees.index') }}">返回列表</a>
    </div>

    <form method="POST" action="{{ $action }}" class="mt-5 max-w-3xl space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="name" class="mb-1 block font-semibold">姓名</label>
                <input id="name" name="name" required maxlength="255"
                       value="{{ old('name', $employee->name) }}">
            </div>

            <div>
                <label for="company_name" class="mb-1 block font-semibold">公司</label>
                <select id="company_name" name="company_name" data-searchable-select
                        data-create-enabled="true" data-placeholder="选择或输入公司名称">
                    <option value="">未填写</option>
                    @if ($companyValue && !$companyNames->contains($companyValue))
                        <option value="{{ $companyValue }}" selected>{{ $companyValue }}</option>
                    @endif
                    @foreach ($companyNames as $companyName)
                        <option value="{{ $companyName }}" @if ($companyValue === $companyName) selected @endif>
                            {{ $companyName }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="supervisor_id" class="mb-1 block font-semibold">上级</label>
                @if (Auth::guard('admin')->user()->role === 'manager')
                    @php($managerSupervisor = $supervisorOptions->first())
                    <input type="hidden" name="supervisor_id" value="{{ optional($managerSupervisor)->id }}">
                    <input value="{{ optional($managerSupervisor)->name }}" readonly>
                @else
                    <select id="supervisor_id" name="supervisor_id" data-searchable-select
                            data-placeholder="无上级">
                        <option value="">无上级</option>
                        @foreach ($supervisorOptions as $supervisor)
                            <option value="{{ $supervisor->id }}"
                                    @if ((string) old('supervisor_id', $employee->supervisor_id) === (string) $supervisor->id) selected @endif>
                                {{ $supervisor->name }}{{ $supervisor->company_name ? ' · '.$supervisor->company_name : '' }}{{ !$supervisor->is_active ? '（离职）' : '' }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div>
                <label for="admin_id" class="mb-1 block font-semibold">关联管理账号</label>
                <select id="admin_id" name="admin_id" data-searchable-select data-placeholder="不关联账号">
                    <option value="">不关联账号</option>
                    @foreach ($accountOptions as $account)
                        <option value="{{ $account->id }}"
                                @if ((string) old('admin_id', $employee->admin_id) === (string) $account->id) selected @endif>
                            {{ $account->name }} · {{ $account->email }}{{ (!$account->is_active || $account->trashed()) ? '（停用）' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="position_ids" class="mb-1 block font-semibold">职位</label>
            <select id="position_ids" name="position_ids[]" multiple data-searchable-select
                    data-placeholder="搜索并选择职位">
                @foreach ($positionOptions as $position)
                    <option value="{{ $position->id }}" @if (in_array((int) $position->id, $selectedPositions, true)) selected @endif>
                        {{ $position->name }} · {{ $position->code }}{{ $position->trashed() ? '（已删除，仅可保留）' : (!$position->is_active ? '（已停用，仅可保留）' : '') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-2 font-semibold">
                <input class="h-4 w-4" type="checkbox" name="is_active" value="1"
                       @if ((bool) old('is_active', $employee->exists ? $employee->is_active : true)) checked @endif>
                在职
            </label>
        </div>

        <div class="flex gap-2 border-t border-gray-200 pt-4">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">
                {{ $editing ? '保存修改' : '创建员工' }}
            </button>
            <a class="button button-secondary no-underline" href="{{ route('employees.index') }}">取消</a>
        </div>
    </form>
@endsection
