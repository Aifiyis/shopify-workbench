@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">工艺管理</h1>
        <div class="flex flex-wrap gap-2">
            @if ($returnUrl)
                <a class="button button-secondary no-underline" href="{{ $returnUrl }}">返回订单处理配置</a>
            @endif
            @can('create', \App\Models\ProcessingCraftNode::class)
                <a class="button bg-green-700 text-white no-underline hover:bg-green-800"
                   href="{{ route('processing-crafts.create', $returnTarget ? ['return_to' => $returnTarget] : []) }}">
                    新增工艺
                </a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('processing-crafts.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
        @if ($returnTarget)
            <input type="hidden" name="return_to" value="{{ $returnTarget }}">
        @endif
        <div class="md:col-span-9">
            <label for="search" class="mb-1 block text-sm font-semibold">搜索</label>
            <input id="search" name="search" value="{{ $search }}" placeholder="工艺名称或完整路径">
        </div>
        <div class="flex items-end gap-2 md:col-span-3">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">查询</button>
            @if ($search !== '')
                <a class="button button-secondary no-underline"
                   href="{{ route('processing-crafts.index', $returnTarget ? ['return_to' => $returnTarget] : []) }}">
                    清除
                </a>
            @endif
        </div>
    </form>

    <div class="mt-5 overflow-x-auto border border-gray-200 bg-white text-sm">
        <table>
            <thead>
                <tr>
                    <th>工艺名称</th>
                    <th>完整路径</th>
                    <th>上级工艺</th>
                    <th>层级</th>
                    <th class="w-28">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($crafts as $craft)
                    <tr>
                        <td class="font-medium" style="padding-left: {{ 10 + substr_count($craft->path, '-') * 16 }}px">
                            {{ $craft->name }}
                        </td>
                        <td>{{ $craft->path }}</td>
                        <td>{{ optional($craft->parent)->path ?: '无' }}</td>
                        <td>{{ substr_count($craft->path, '-') + 1 }}</td>
                        <td>
                            <div class="flex items-center gap-3 whitespace-nowrap">
                                @can('update', $craft)
                                    <a href="{{ route('processing-crafts.edit', array_merge([$craft], $returnTarget ? ['return_to' => $returnTarget] : [])) }}">编辑</a>
                                @endcan
                                @can('delete', $craft)
                                    <form id="delete-processing-craft-{{ $craft->id }}"
                                          method="POST"
                                          action="{{ route('processing-crafts.destroy', array_merge([$craft], $returnTarget ? ['return_to' => $returnTarget] : [])) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                class="cursor-pointer border-0 bg-transparent p-0 text-red-700 underline"
                                                data-delete-trigger
                                                data-delete-form="delete-processing-craft-{{ $craft->id }}"
                                                data-delete-title="删除工艺"
                                                data-delete-message="确定删除 {{ $craft->path }} 吗？">
                                            删除
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-gray-500">没有符合条件的工艺。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($crafts->hasPages())
        <nav class="mt-4 flex items-center justify-end gap-3 text-sm" aria-label="工艺分页">
            @if ($crafts->onFirstPage())
                <span class="text-gray-400">上一页</span>
            @else
                <a href="{{ $crafts->previousPageUrl() }}">上一页</a>
            @endif
            <span>第 {{ $crafts->currentPage() }} 页，共 {{ $crafts->lastPage() }} 页</span>
            @if ($crafts->hasMorePages())
                <a href="{{ $crafts->nextPageUrl() }}">下一页</a>
            @else
                <span class="text-gray-400">下一页</span>
            @endif
        </nav>
    @endif
@endsection
