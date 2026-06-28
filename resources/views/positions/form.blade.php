@extends('layouts.app')

@section('content')
    @php
        $editing = $position->exists;
        $action = $editing ? route('positions.update', $position) : route('positions.store');
        $selectedPermissions = array_map('intval', (array) old('permission_ids', $selectedPermissionIds));
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">{{ $editing ? '编辑职位' : '新增职位' }}</h1>
        <a class="button button-secondary no-underline" href="{{ route('positions.index') }}">返回列表</a>
    </div>

    <form method="POST" action="{{ $action }}" class="mt-5 max-w-3xl space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="name" class="mb-1 block font-semibold">职位名称</label>
                <input id="name" name="name" required maxlength="255"
                       value="{{ old('name', $position->name) }}">
            </div>

            <div>
                <label for="code" class="mb-1 block font-semibold">职位编码</label>
                @if ($editing)
                    <input id="code" value="{{ $position->code }}" readonly>
                @else
                    <input id="code" name="code" required maxlength="255"
                           value="{{ old('code') }}" placeholder="例如：order_review">
                @endif
            </div>
        </div>

        @if ($canAssignPermissions)
            <div>
                <label for="permission_ids" class="mb-1 block font-semibold">可分配权限</label>
                <select id="permission_ids" name="permission_ids[]" multiple data-searchable-select
                        data-placeholder="搜索并选择权限">
                    @foreach ($permissionOptions as $permission)
                        <option value="{{ $permission->id }}"
                                @if (in_array((int) $permission->id, $selectedPermissions, true)) selected @endif>
                            {{ $permission->name }} · {{ $permission->code }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($lockedPermissions->isNotEmpty())
            <div class="border border-gray-200 bg-gray-50 p-3">
                <div class="font-semibold">不可分配（保留）</div>
                <div class="mt-2 text-sm text-gray-600">
                    @foreach ($lockedPermissions as $permission)
                        <span class="mr-3 inline-block">{{ $permission->name }} · {{ $permission->code }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-2 font-semibold">
                <input class="h-4 w-4" type="checkbox" name="is_active" value="1"
                       @if ((bool) old('is_active', $position->exists ? $position->is_active : true)) checked @endif>
                启用
            </label>
        </div>

        <div class="flex gap-2 border-t border-gray-200 pt-4">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">
                {{ $editing ? '保存修改' : '创建职位' }}
            </button>
            <a class="button button-secondary no-underline" href="{{ route('positions.index') }}">取消</a>
        </div>
    </form>
@endsection
