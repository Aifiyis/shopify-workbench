@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">员工与职位</h1>
        @can('create', \App\Models\Position::class)
            <a class="button bg-green-700 text-white no-underline hover:bg-green-800"
               href="{{ route('positions.create') }}">
                新增职位
            </a>
        @endcan
    </div>

    <nav class="mt-4 flex gap-5 border-b border-gray-200" aria-label="员工与职位分类">
        @can('viewAny', \App\Models\Employee::class)
            <a class="border-b-2 border-transparent px-1 py-3 font-semibold text-gray-500 no-underline"
               href="{{ route('employees.index') }}">
                员工档案
            </a>
        @endcan
        <a class="border-b-2 border-green-700 px-1 py-3 font-semibold text-green-800 no-underline"
           href="{{ route('positions.index') }}" aria-current="page">
            职位权限
        </a>
    </nav>

    <form method="GET" action="{{ route('positions.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
        <div class="md:col-span-7">
            <label for="search" class="mb-1 block text-sm font-semibold">搜索</label>
            <input id="search" name="search" value="{{ $search }}" placeholder="职位名称、编码或权限">
        </div>
        <div class="md:col-span-2">
            <label for="status" class="mb-1 block text-sm font-semibold">启用状态</label>
            <select id="status" name="status">
                <option value="">全部</option>
                <option value="active" @if ($status === 'active') selected @endif>启用</option>
                <option value="inactive" @if ($status === 'inactive') selected @endif>停用</option>
            </select>
        </div>
        <div class="flex items-end gap-2 md:col-span-3">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">查询</button>
            @if ($search !== '' || $status !== '')
                <a class="button button-secondary no-underline" href="{{ route('positions.index') }}">清除</a>
            @endif
        </div>
    </form>

    <div class="mt-5 overflow-x-auto border border-gray-200 bg-white text-sm">
        <table>
            <thead>
                <tr>
                    <th>职位名称</th>
                    <th>编码</th>
                    <th>权限</th>
                    <th class="w-24">员工数</th>
                    <th class="w-20">状态</th>
                    <th class="w-28">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td class="font-medium">{{ $position->name }}</td>
                        <td>{{ $position->code }}</td>
                        <td>{{ $position->permissions->pluck('name')->implode('、') ?: '未分配' }}</td>
                        <td>{{ $position->employees_count }}</td>
                        <td>{{ $position->is_active ? '启用' : '停用' }}</td>
                        <td>
                            <div class="flex items-center gap-3 whitespace-nowrap">
                                @can('update', $position)
                                    <a href="{{ route('positions.edit', $position) }}">编辑</a>
                                @endcan
                                @can('delete', $position)
                                    <form id="delete-position-{{ $position->id }}"
                                          method="POST" action="{{ route('positions.destroy', $position) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                class="cursor-pointer border-0 bg-transparent p-0 text-red-700 underline"
                                                data-delete-trigger
                                                data-delete-form="delete-position-{{ $position->id }}"
                                                data-delete-title="删除职位"
                                                data-delete-message="确定删除职位 {{ $position->name }} 吗？">
                                            删除
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-500">没有符合条件的职位。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($positions->hasPages())
        <nav class="mt-4 flex items-center justify-end gap-3 text-sm" aria-label="职位分页">
            @if ($positions->onFirstPage())
                <span class="text-gray-400">上一页</span>
            @else
                <a href="{{ $positions->previousPageUrl() }}">上一页</a>
            @endif
            <span>第 {{ $positions->currentPage() }} 页，共 {{ $positions->lastPage() }} 页</span>
            @if ($positions->hasMorePages())
                <a href="{{ $positions->nextPageUrl() }}">下一页</a>
            @else
                <span class="text-gray-400">下一页</span>
            @endif
        </nav>
    @endif
@endsection
