@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">员工与职位</h1>
        @can('create', \App\Models\Employee::class)
            <a class="button bg-green-700 text-white no-underline hover:bg-green-800"
               href="{{ route('employees.create') }}">
                新增员工
            </a>
        @endcan
    </div>

    <nav class="mt-4 flex gap-5 border-b border-gray-200" aria-label="员工与职位分类">
        <a class="border-b-2 border-green-700 px-1 py-3 font-semibold text-green-800 no-underline"
           href="{{ route('employees.index') }}" aria-current="page">
            员工档案
        </a>
        @can('viewAny', \App\Models\Position::class)
            <a class="border-b-2 border-transparent px-1 py-3 font-semibold text-gray-500 no-underline"
               href="{{ route('positions.index') }}">
                职位权限
            </a>
        @endcan
    </nav>

    <form method="GET" action="{{ route('employees.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
        <div class="md:col-span-7">
            <label for="search" class="mb-1 block text-sm font-semibold">搜索</label>
            <input id="search" name="search" value="{{ $search }}"
                   placeholder="姓名、公司、上级、账号或职位">
        </div>
        <div class="md:col-span-2">
            <label for="status" class="mb-1 block text-sm font-semibold">在职状态</label>
            <select id="status" name="status">
                <option value="">全部</option>
                <option value="active" @if ($status === 'active') selected @endif>在职</option>
                <option value="inactive" @if ($status === 'inactive') selected @endif>离职</option>
            </select>
        </div>
        <div class="flex items-end gap-2 md:col-span-3">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">查询</button>
            @if ($search !== '' || $status !== '')
                <a class="button button-secondary no-underline" href="{{ route('employees.index') }}">清除</a>
            @endif
        </div>
    </form>

    <div class="mt-5 overflow-x-auto border border-gray-200 bg-white text-sm">
        <table>
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>公司</th>
                    <th>上级</th>
                    <th>关联账号</th>
                    <th>职位</th>
                    <th class="w-20">状态</th>
                    <th class="w-28">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td class="font-medium">{{ $employee->name }}</td>
                        <td>{{ $employee->company_name ?: '未填写' }}</td>
                        <td>{{ optional($employee->supervisor)->name ?: '无' }}</td>
                        <td>
                            @if ($employee->admin)
                                {{ $employee->admin->name }}<br>
                                <span class="text-xs text-gray-500">{{ $employee->admin->email }}</span>
                            @else
                                未关联
                            @endif
                        </td>
                        <td>
                            {{ $employee->positions->pluck('name')->implode('、') ?: '未分配' }}
                        </td>
                        <td>{{ $employee->is_active ? '在职' : '离职' }}</td>
                        <td>
                            <div class="flex items-center gap-3 whitespace-nowrap">
                                @can('update', $employee)
                                    <a href="{{ route('employees.edit', $employee) }}">编辑</a>
                                @endcan
                                @if ((int) $employee->admin_id !== (int) Auth::guard('admin')->id())
                                    @can('delete', $employee)
                                        <form id="delete-employee-{{ $employee->id }}"
                                              method="POST" action="{{ route('employees.destroy', $employee) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    class="cursor-pointer border-0 bg-transparent p-0 text-red-700 underline"
                                                    data-delete-trigger
                                                    data-delete-form="delete-employee-{{ $employee->id }}"
                                                    data-delete-title="删除员工档案"
                                                    data-delete-message="确定删除 {{ $employee->name }} 的员工档案吗？">
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
                        <td colspan="7" class="py-10 text-center text-gray-500">没有符合条件的员工。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($employees->hasPages())
        <nav class="mt-4 flex items-center justify-end gap-3 text-sm" aria-label="员工分页">
            @if ($employees->onFirstPage())
                <span class="text-gray-400">上一页</span>
            @else
                <a href="{{ $employees->previousPageUrl() }}">上一页</a>
            @endif
            <span>第 {{ $employees->currentPage() }} 页，共 {{ $employees->lastPage() }} 页</span>
            @if ($employees->hasMorePages())
                <a href="{{ $employees->nextPageUrl() }}">下一页</a>
            @else
                <span class="text-gray-400">下一页</span>
            @endif
        </nav>
    @endif
@endsection
