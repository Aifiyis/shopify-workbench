<div class="overflow-x-auto border border-gray-200 bg-white">
    <table>
        <thead>
            <tr>
                <th>产品类型</th>
                <th class="w-32">SKU 数量</th>
                <th class="w-40">订单处理配置</th>
                <th class="w-36">操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($productTypes as $productType)
                <tr>
                    <td class="font-medium">{{ $productType->chinese_name }}</td>
                    <td>{{ $productType->sku_matches_count }}</td>
                    <td>
                        @if ($productType->active_processing_config_count > 0)
                            <span class="text-green-800">已配置</span>
                        @elseif ($productType->all_processing_config_count > 0)
                            <span class="text-gray-500">配置已删除</span>
                        @else
                            <span class="text-gray-500">未配置</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-3 whitespace-nowrap">
                            @can('update', $productType)
                                <a href="{{ route('product-types.edit', $productType) }}">编辑</a>
                            @endcan
                            @can('delete', $productType)
                                <form id="delete-type-{{ $productType->id }}" method="POST" action="{{ route('product-types.destroy', $productType) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            class="border-0 bg-transparent p-0 text-red-700 underline cursor-pointer"
                                            data-delete-trigger
                                            data-delete-form="delete-type-{{ $productType->id }}"
                                            data-delete-title="删除产品类型"
                                            data-delete-message="确定删除 {{ $productType->chinese_name }} 吗？">
                                        删除
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-10 text-center text-gray-500">没有符合条件的产品类型。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<x-business-pagination
    :paginator="$productTypes"
    page-name="type_page"
    per-page-name="type_per_page"
    aria-label="产品类型分页" />
