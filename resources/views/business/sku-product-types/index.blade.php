@extends('layouts.app')

@section('content')
    @php
        $tabQuery = request()->except(['tab', 'sku_page', 'type_page']);
        $canManageSku = Gate::forUser(Auth::guard('admin')->user())
            ->allows('create', \App\Models\SkuMatchProductType::class);
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <div>
            <h1 class="m-0">SKU 与产品类型</h1>
        </div>
        @if ($activeTab === 'skus')
            @can('create', \App\Models\SkuMatchProductType::class)
                <a class="button bg-green-700 text-white no-underline hover:bg-green-800" href="{{ route('sku-product-types.create') }}">新增 SKU 映射</a>
            @endcan
        @else
            @can('create', \App\Models\ProductType::class)
                <a class="button bg-green-700 text-white no-underline hover:bg-green-800" href="{{ route('product-types.create') }}">新增产品类型</a>
            @endcan
        @endif
    </div>

    <nav class="mt-4 flex gap-5 border-b border-gray-200" aria-label="数据分类">
        <a class="border-b-2 px-1 py-3 font-semibold no-underline {{ $activeTab === 'skus' ? 'border-green-700 text-green-800' : 'border-transparent text-gray-500' }}"
           href="{{ route('sku-product-types.index', array_merge($tabQuery, ['tab' => 'skus'])) }}">
            SKU 映射
        </a>
        <a class="border-b-2 px-1 py-3 font-semibold no-underline {{ $activeTab === 'types' ? 'border-green-700 text-green-800' : 'border-transparent text-gray-500' }}"
           href="{{ route('sku-product-types.index', array_merge($tabQuery, ['tab' => 'types'])) }}">
            产品类型
        </a>
    </nav>

    <form method="GET" action="{{ route('sku-product-types.index') }}" class="mt-4 grid gap-3 md:grid-cols-12">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <div class="md:col-span-{{ $activeTab === 'skus' ? '6' : '9' }}">
            <label for="search" class="mb-1 block text-sm font-semibold">搜索</label>
            <input id="search" name="search" value="{{ $search }}" placeholder="{{ $activeTab === 'skus' ? '原始 SKU、清洗后 SKU、产品类型或上品人' : '产品类型名称' }}">
        </div>
        @if ($activeTab === 'skus')
            <div class="md:col-span-3">
                <label for="product_type_id" class="mb-1 block text-sm font-semibold">产品类型筛选</label>
                <select id="product_type_id" name="product_type_id" data-searchable-select data-placeholder="全部产品类型">
                    <option value="">全部产品类型</option>
                    @foreach ($typeOptions as $typeOption)
                        <option value="{{ $typeOption->id }}" @if ((string) $selectedProductTypeId === (string) $typeOption->id) selected @endif>
                            {{ $typeOption->chinese_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="flex items-end gap-2 md:col-span-3">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">查询</button>
            @if ($search !== '' || $selectedProductTypeId)
                <a class="button button-secondary no-underline" href="{{ route('sku-product-types.index', ['tab' => $activeTab]) }}">清除</a>
            @endif
        </div>
    </form>

    <div class="mt-5">
        @if ($activeTab === 'skus')
            @include('business.sku-product-types._sku-table')
            @if ($canManageSku)
                <x-sku-bulk-edit-dialog
                    :product-types="$typeOptions"
                    :eligible-listers="$eligibleListers" />
            @endif
        @else
            @include('business.sku-product-types._type-table')
        @endif
    </div>
@endsection
