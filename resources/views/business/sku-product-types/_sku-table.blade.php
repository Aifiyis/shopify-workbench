<div data-sku-bulk-root>
    @if ($canManageSku)
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-600">已选择 <strong data-sku-bulk-count>0</strong> 条</span>
            <button type="button" class="button button-secondary" data-sku-bulk-select-group>全选同组</button>
            <button type="button" class="button bg-green-700 text-white hover:bg-green-800" data-sku-bulk-open disabled>批量修改</button>
            <span class="text-sm text-red-700" data-sku-bulk-feedback role="status" aria-live="polite"></span>
        </div>
    @endif
    <div class="overflow-x-auto border border-gray-200 bg-white">
        <table>
        <thead>
            <tr>
                @if ($canManageSku)
                    <th class="w-12"><span class="sr-only">选择</span></th>
                @endif
                <th>原始 SKU</th>
                <th>清洗后 SKU</th>
                <th>产品类型</th>
                <th>上品人</th>
                <th class="w-36">操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($skuMatches as $skuMatch)
                <tr>
                    @if ($canManageSku)
                        <td>
                            <input type="checkbox"
                                   value="{{ $skuMatch->id }}"
                                   aria-label="选择 {{ $skuMatch->original_sku }}"
                                   data-sku-bulk-checkbox
                                   data-cleaned-sku="{{ $skuMatch->cleaned_sku }}">
                        </td>
                    @endif
                    <td class="font-medium">{{ $skuMatch->original_sku }}</td>
                    <td>{{ $skuMatch->cleaned_sku }}</td>
                    <td>{{ optional($skuMatch->productType)->chinese_name ?: $skuMatch->chinese_name }}</td>
                    <td>{{ optional($skuMatch->productListerEmployee)->name ?: ($skuMatch->product_lister ?: '未指定') }}</td>
                    <td>
                        <div class="flex items-center gap-3 whitespace-nowrap">
                            @can('update', $skuMatch)
                                <a href="{{ route('sku-product-types.edit', $skuMatch) }}">编辑</a>
                            @endcan
                            @can('delete', $skuMatch)
                                <form id="delete-sku-{{ $skuMatch->id }}" method="POST" action="{{ route('sku-product-types.destroy', $skuMatch) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            class="border-0 bg-transparent p-0 text-red-700 underline cursor-pointer"
                                            data-delete-trigger
                                            data-delete-form="delete-sku-{{ $skuMatch->id }}"
                                            data-delete-title="删除 SKU 映射"
                                            data-delete-message="确定删除 {{ $skuMatch->original_sku }} 吗？">
                                        删除
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $canManageSku ? 6 : 5 }}" class="py-10 text-center text-gray-500">没有符合条件的 SKU 映射。</td>
                </tr>
            @endforelse
        </tbody>
        </table>
    </div>
</div>

<x-business-pagination
    :paginator="$skuMatches"
    page-name="sku_page"
    per-page-name="sku_per_page"
    aria-label="SKU 映射分页" />
