@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">订单处理配置</h1>
        @can('create', \App\Models\ProductProcessingCraft::class)
            <a class="button bg-green-700 text-white no-underline hover:bg-green-800" href="{{ route('order-processing.create') }}">新增配置</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('order-processing.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
        <div class="md:col-span-4">
            <label for="search" class="mb-1 block text-sm font-semibold">搜索</label>
            <input id="search" name="search" value="{{ $search }}" placeholder="产品类型、工艺、结算、模板或员工">
        </div>
        <div class="md:col-span-3">
            <label for="product_type_id" class="mb-1 block text-sm font-semibold">产品类型</label>
            <select id="product_type_id" name="product_type_id" data-searchable-select data-placeholder="全部产品类型">
                <option value="">全部产品类型</option>
                @foreach ($productTypes as $productType)
                    <option value="{{ $productType->id }}" @if ((string) $selectedProductTypeId === (string) $productType->id) selected @endif>
                        {{ $productType->chinese_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-3">
            <label for="craft_id" class="mb-1 block text-sm font-semibold">工艺</label>
            <select id="craft_id" name="craft_id" data-searchable-select data-option-type="craft" data-placeholder="全部工艺">
                <option value="">全部工艺</option>
                @foreach ($crafts as $craft)
                    <option value="{{ $craft->id }}"
                            data-depth="{{ substr_count($craft->path, '-') }}"
                            data-path="{{ $craft->path }}"
                            @if ((string) $selectedCraftId === (string) $craft->id) selected @endif>
                        {{ $craft->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2 md:col-span-2">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">查询</button>
            @if ($search !== '' || $selectedProductTypeId || $selectedCraftId)
                <a class="button button-secondary no-underline" href="{{ route('order-processing.index') }}">清除</a>
            @endif
        </div>
    </form>

    @php
        $employeeNames = function ($employees) {
            return $employees->map(function ($employee) {
                if ($employee->trashed()) {
                    return $employee->name.'（已删除）';
                }
                if (!$employee->is_active) {
                    return $employee->name.'（已停用）';
                }
                return $employee->name;
            })->implode('、');
        };
    @endphp

    <div class="mt-5 overflow-x-auto border border-gray-200 bg-white text-sm">
        <table>
            <thead>
                <tr>
                    <th>产品类型</th>
                    <th>工艺</th>
                    <th>订单处理人</th>
                    <th>图画处理人</th>
                    <th>采购处理人</th>
                    <th>结算方式</th>
                    <th>表格模板</th>
                    <th>模板说明</th>
                    <th class="w-28">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($configurations as $configuration)
                    <tr>
                        <td class="font-medium">
                            {{ optional($configuration->productType)->chinese_name ?: $configuration->chinese_name }}
                            @if ($configuration->productType && $configuration->productType->trashed())
                                <span class="text-gray-500">（已删除）</span>
                            @endif
                        </td>
                        <td>
                            @if ($configuration->craft)
                                {{ $configuration->craft->path }}@if ($configuration->craft->trashed())<span class="text-gray-500">（已删除）</span>@endif
                            @else
                                <span class="text-gray-500">未指定</span>
                            @endif
                        </td>
                        <td>{{ $employeeNames($configuration->orderProcessorEmployees) ?: '未指定' }}</td>
                        <td>{{ $employeeNames($configuration->artworkProcessorEmployees) ?: '未指定' }}</td>
                        <td>{{ $employeeNames($configuration->procurementProcessorEmployees) ?: '未指定' }}</td>
                        <td>{{ $configuration->settlement_method ?: '未指定' }}</td>
                        <td>{{ $configuration->spreadsheet_template ?: '未指定' }}</td>
                        <td>{{ $configuration->spreadsheet_template_description ?: '未填写' }}</td>
                        <td>
                            <div class="flex items-center gap-3 whitespace-nowrap">
                                @can('update', $configuration)
                                    <a href="{{ route('order-processing.edit', $configuration) }}">编辑</a>
                                @endcan
                                @can('delete', $configuration)
                                    <form id="delete-order-processing-{{ $configuration->id }}" method="POST" action="{{ route('order-processing.destroy', $configuration) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                class="cursor-pointer border-0 bg-transparent p-0 text-red-700 underline"
                                                data-delete-trigger
                                                data-delete-form="delete-order-processing-{{ $configuration->id }}"
                                                data-delete-title="删除订单处理配置"
                                                data-delete-message="确定删除 {{ $configuration->chinese_name }} 的订单处理配置吗？">
                                            删除
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-10 text-center text-gray-500">没有符合条件的订单处理配置。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-business-pagination
        :paginator="$configurations"
        page-name="page"
        per-page-name="per_page"
        aria-label="订单处理配置分页" />
@endsection
