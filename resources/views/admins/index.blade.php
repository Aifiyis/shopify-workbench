@extends('layouts.app')

@section('content')
    @php
        $roleLabels = [
            'super' => '超级管理员',
            'manager' => '管理员',
            'employee' => '员工',
        ];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">管理员账号</h1>
        @can('create', [\App\Models\Admin::class, 'employee'])
            <a class="button bg-green-700 text-white no-underline hover:bg-green-800"
               href="{{ route('admins.create') }}">
                新增账号
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('admins.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
        <div class="md:col-span-9">
            <label for="search" class="mb-1 block text-sm font-semibold">搜索账号</label>
            <input id="search" name="search" value="{{ $search }}"
                   placeholder="姓名、邮箱、公司、角色、员工档案或上级账号">
        </div>
        <div class="flex items-end gap-2 md:col-span-3">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">查询</button>
            @if ($search !== '')
                <a class="button button-secondary no-underline" href="{{ route('admins.index') }}">清除</a>
            @endif
        </div>
    </form>

    <div class="mt-5 overflow-x-auto border border-gray-200 bg-white text-sm">
        <table>
            <thead>
                <tr>
                    <th>账号</th>
                    <th class="w-28">角色</th>
                    <th>公司</th>
                    <th>员工档案</th>
                    <th>上级账号</th>
                    <th class="w-24">店铺</th>
                    <th class="w-20">状态</th>
                    <th class="w-28">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($admins as $admin)
                    <tr>
                        <td>
                            <span class="font-medium">{{ $admin->name }}</span><br>
                            <span class="text-xs text-gray-500">{{ $admin->email }}</span>
                        </td>
                        <td>{{ $roleLabels[$admin->role] ?? $admin->role }}</td>
                        <td>{{ $admin->company_name ?: '未填写' }}</td>
                        <td>{{ optional($admin->employee)->name ?: '未关联' }}</td>
                        <td>
                            @if ($admin->parent)
                                {{ $admin->parent->name }}
                                @if ($admin->parent->trashed())
                                    <span class="text-xs text-red-700">（已删除）</span>
                                @endif
                            @else
                                无
                            @endif
                        </td>
                        <td>{{ $admin->stores_count }} 个</td>
                        <td>{{ $admin->is_active ? '启用' : '停用' }}</td>
                        <td>
                            <div class="flex items-center gap-3 whitespace-nowrap">
                                @can('update', $admin)
                                    <a href="{{ route('admins.edit', $admin) }}">编辑</a>
                                @endcan
                                @if ((int) $admin->id !== (int) Auth::guard('admin')->id())
                                    @can('delete', $admin)
                                        <form id="delete-admin-{{ $admin->id }}"
                                              method="POST" action="{{ route('admins.destroy', $admin) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    class="cursor-pointer border-0 bg-transparent p-0 text-red-700 underline"
                                                    data-delete-trigger
                                                    data-delete-form="delete-admin-{{ $admin->id }}"
                                                    data-delete-title="删除管理员账号"
                                                    data-delete-message="确定删除 {{ $admin->name }} 的管理员账号吗？员工档案将保留。">
                                                删除
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-10 text-center text-gray-500">没有符合条件的管理员账号。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($admins->hasPages())
        <nav class="mt-4 flex items-center justify-end gap-3 text-sm" aria-label="管理员账号分页">
            @if ($admins->onFirstPage())
                <span class="text-gray-400">上一页</span>
            @else
                <a href="{{ $admins->previousPageUrl() }}">上一页</a>
            @endif
            <span>第 {{ $admins->currentPage() }} 页，共 {{ $admins->lastPage() }} 页</span>
            @if ($admins->hasMorePages())
                <a href="{{ $admins->nextPageUrl() }}">下一页</a>
            @else
                <span class="text-gray-400">下一页</span>
            @endif
        </nav>
    @endif
@endsection
