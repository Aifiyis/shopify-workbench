<div class="overflow-x-auto border border-gray-200 bg-white">
    <table>
        <thead>
            <tr>
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
                    <td colspan="5" class="py-10 text-center text-gray-500">没有符合条件的 SKU 映射。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($skuMatches->hasPages())
    <nav class="mt-4 flex items-center justify-end gap-3 text-sm" aria-label="SKU 映射分页">
        @if ($skuMatches->onFirstPage())
            <span class="text-gray-400">上一页</span>
        @else
            <a href="{{ $skuMatches->previousPageUrl() }}">上一页</a>
        @endif
        <span>第 {{ $skuMatches->currentPage() }} 页，共 {{ $skuMatches->lastPage() }} 页</span>
        @if ($skuMatches->hasMorePages())
            <a href="{{ $skuMatches->nextPageUrl() }}">下一页</a>
        @else
            <span class="text-gray-400">下一页</span>
        @endif
    </nav>
@endif
