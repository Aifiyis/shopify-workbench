<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductTypeRequest;
use App\Http\Requests\UpdateProductTypeRequest;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductType::class);

        return redirect()->route(
            'sku-product-types.index',
            array_merge($request->query(), ['tab' => 'types'])
        );
    }

    public function create()
    {
        $this->authorize('create', ProductType::class);

        return view('business.sku-product-types.form', [
            'entity' => 'type',
            'productType' => new ProductType(),
        ]);
    }

    public function store(StoreProductTypeRequest $request)
    {
        $this->authorize('create', ProductType::class);

        ProductType::create($request->validated());

        return redirect()
            ->route('sku-product-types.index', ['tab' => 'types'])
            ->with('success', '产品类型已创建。');
    }

    public function quickStore(StoreProductTypeRequest $request)
    {
        $this->authorize('create', ProductType::class);

        $name = $request->validated()['chinese_name'];
        $duplicate = ProductType::withTrashed()
            ->where('chinese_name', $name)
            ->first();

        if ($duplicate) {
            $response = [
                'message' => $duplicate->trashed()
                    ? '该产品类型存在于已删除记录中，不能重复创建。'
                    : '该产品类型已存在，请前往编辑。',
            ];

            if (!$duplicate->trashed()) {
                $response['edit_url'] = route('product-types.edit', $duplicate->id);
            }

            return response()->json($response, 422);
        }

        $productType = ProductType::create(['chinese_name' => $name]);

        return response()->json([
            'id' => $productType->id,
            'chinese_name' => $productType->chinese_name,
        ]);
    }

    public function edit(ProductType $productType)
    {
        $this->authorize('update', $productType);

        return view('business.sku-product-types.form', [
            'entity' => 'type',
            'productType' => $productType,
        ]);
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType)
    {
        $this->authorize('update', $productType);

        $name = $request->validated()['chinese_name'];

        DB::transaction(function () use ($productType, $name) {
            $productType->update(['chinese_name' => $name]);

            SkuMatchProductType::withTrashed()
                ->where('product_type_id', $productType->id)
                ->update(['chinese_name' => $name]);

            ProductProcessingCraft::withTrashed()
                ->where('product_type_id', $productType->id)
                ->update(['chinese_name' => $name]);
        });

        return redirect()
            ->route('sku-product-types.index', ['tab' => 'types'])
            ->with('success', '产品类型已更新，关联快照已同步。');
    }

    public function destroy(ProductType $productType)
    {
        $this->authorize('delete', $productType);

        $hasSkuReference = SkuMatchProductType::withTrashed()
            ->where('product_type_id', $productType->id)
            ->exists();
        $hasProcessingReference = ProductProcessingCraft::withTrashed()
            ->where('product_type_id', $productType->id)
            ->exists();

        if ($hasSkuReference || $hasProcessingReference) {
            return redirect()
                ->route('sku-product-types.index', ['tab' => 'types'])
                ->with('error', '该产品类型仍被 SKU 映射或订单处理配置引用，无法删除。');
        }

        $productType->delete();

        return redirect()
            ->route('sku-product-types.index', ['tab' => 'types'])
            ->with('success', '产品类型已删除。');
    }
}
