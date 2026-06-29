@props(['productTypes', 'eligibleListers'])

<dialog id="sku-bulk-edit-dialog" class="sku-bulk-dialog" aria-labelledby="sku-bulk-edit-title">
    <form method="POST" action="{{ route('sku-product-types.bulk-update') }}" class="sku-bulk-dialog-content">
        @csrf
        <h2 id="sku-bulk-edit-title">批量修改 SKU 映射</h2>
        <p class="sku-bulk-dialog-summary">
            已选择 <strong data-sku-bulk-dialog-count>0</strong> 条，清洗后 SKU：
            <strong data-sku-bulk-dialog-group></strong>
        </p>
        <div data-sku-bulk-hidden-inputs></div>
        <input type="hidden" name="return_query[tab]" value="skus">
        @foreach (['search', 'product_type_id', 'sku_page', 'sku_per_page'] as $queryName)
            @if (request()->filled($queryName))
                <input type="hidden" name="return_query[{{ $queryName }}]" value="{{ request()->query($queryName) }}">
            @endif
        @endforeach

        <div class="mt-5 space-y-4">
            <div>
                <label for="bulk_product_type_id" class="mb-1 block font-semibold">产品类型</label>
                <select id="bulk_product_type_id"
                        name="product_type_id"
                        required
                        data-searchable-select
                        data-placeholder="请选择产品类型">
                    <option value="">请选择产品类型</option>
                    @foreach ($productTypes as $productType)
                        <option value="{{ $productType->id }}">{{ $productType->chinese_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bulk_product_lister_employee_id" class="mb-1 block font-semibold">上品人</label>
                <select id="bulk_product_lister_employee_id"
                        name="product_lister_employee_id"
                        data-searchable-select
                        data-placeholder="不选择（清空上品人）">
                    <option value="">不选择（清空上品人）</option>
                    @foreach ($eligibleListers as $lister)
                        <option value="{{ $lister->id }}">{{ $lister->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="sku-bulk-dialog-actions">
            <button type="button" class="button button-secondary" data-sku-bulk-cancel>取消</button>
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">保存批量修改</button>
        </div>
    </form>
</dialog>
