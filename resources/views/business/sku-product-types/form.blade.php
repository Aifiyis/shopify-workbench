@extends('layouts.app')

@section('content')
    @if ($entity === 'sku')
        @php
            $editing = $skuMatch->exists;
            $action = $editing ? route('sku-product-types.update', $skuMatch) : route('sku-product-types.store');
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
            <h1 class="m-0">{{ $editing ? '编辑 SKU 映射' : '新增 SKU 映射' }}</h1>
            <a class="button button-secondary no-underline" href="{{ route('sku-product-types.index') }}">返回列表</a>
        </div>

        <form method="POST" action="{{ $action }}" class="mt-5 max-w-3xl space-y-5">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="original_sku" class="mb-1 block font-semibold">原始 SKU</label>
                    <input id="original_sku" name="original_sku" required maxlength="255" value="{{ old('original_sku', $skuMatch->original_sku) }}">
                </div>
                <div>
                    <label for="cleaned_sku" class="mb-1 block font-semibold">清洗后 SKU</label>
                    <input id="cleaned_sku" name="cleaned_sku" required maxlength="255" value="{{ old('cleaned_sku', $skuMatch->cleaned_sku) }}">
                </div>
            </div>

            <div>
                <label for="product_type_id" class="mb-1 block font-semibold">产品类型</label>
                <select id="product_type_id"
                        name="product_type_id"
                        required
                        data-searchable-select
                        data-placeholder="请选择产品类型"
                        @can('create', \App\Models\ProductType::class)
                            data-quick-create-url="{{ route('product-types.quick-store') }}"
                            data-quick-create-field="chinese_name"
                            data-quick-create-error-target="#product-type-create-error"
                            data-quick-create-edit-label="编辑已有产品类型"
                        @endcan>
                    <option value="">请选择产品类型</option>
                    @foreach ($productTypes as $productType)
                        <option value="{{ $productType->id }}" @if ((string) old('product_type_id', $skuMatch->product_type_id) === (string) $productType->id) selected @endif>
                            {{ $productType->chinese_name }}
                        </option>
                    @endforeach
                </select>
                @can('create', \App\Models\ProductType::class)
                    <div id="product-type-create-error" class="mt-1 text-sm text-red-700" role="alert" aria-live="polite"></div>
                @endcan
            </div>

            <div>
                <label for="product_lister_employee_id" class="mb-1 block font-semibold">上品人</label>
                <select id="product_lister_employee_id" name="product_lister_employee_id" data-searchable-select data-placeholder="可不选择">
                    <option value="">不选择</option>
                    @foreach ($eligibleListers as $lister)
                        <option value="{{ $lister->id }}" @if ((string) old('product_lister_employee_id', $skuMatch->product_lister_employee_id) === (string) $lister->id) selected @endif>
                            {{ $lister->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 border-t border-gray-200 pt-4">
                <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">{{ $editing ? '保存修改' : '创建映射' }}</button>
                <a class="button button-secondary no-underline" href="{{ route('sku-product-types.index') }}">取消</a>
            </div>
        </form>
    @else
        @php
            $editing = $productType->exists;
            $action = $editing ? route('product-types.update', $productType) : route('product-types.store');
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
            <h1 class="m-0">{{ $editing ? '编辑产品类型' : '新增产品类型' }}</h1>
            <a class="button button-secondary no-underline" href="{{ route('sku-product-types.index', ['tab' => 'types']) }}">返回列表</a>
        </div>

        <form method="POST" action="{{ $action }}" class="mt-5 max-w-xl space-y-5">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div>
                <label for="chinese_name" class="mb-1 block font-semibold">产品类型名称</label>
                <input id="chinese_name" name="chinese_name" required maxlength="255" value="{{ old('chinese_name', $productType->chinese_name) }}">
            </div>

            <div class="flex gap-2 border-t border-gray-200 pt-4">
                <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">{{ $editing ? '保存修改' : '创建类型' }}</button>
                <a class="button button-secondary no-underline" href="{{ route('sku-product-types.index', ['tab' => 'types']) }}">取消</a>
            </div>
        </form>
    @endif
@endsection
