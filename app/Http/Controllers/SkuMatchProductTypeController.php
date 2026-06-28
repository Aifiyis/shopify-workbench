<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSkuMatchProductTypeRequest;
use App\Http\Requests\UpdateSkuMatchProductTypeRequest;
use App\Models\Employee;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use Illuminate\Http\Request;

class SkuMatchProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SkuMatchProductType::class);
        $this->authorize('viewAny', ProductType::class);

        $search = trim((string) $request->query('search', ''));
        $productTypeId = $request->query('product_type_id');

        $skuQuery = SkuMatchProductType::query()
            ->with(['productType', 'productListerEmployee'])
            ->orderBy('original_sku');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $skuQuery->where(function ($query) use ($like) {
                $query
                    ->where('original_sku', 'like', $like)
                    ->orWhere('cleaned_sku', 'like', $like)
                    ->orWhere('chinese_name', 'like', $like)
                    ->orWhere('product_lister', 'like', $like)
                    ->orWhereHas('productType', function ($productTypeQuery) use ($like) {
                        $productTypeQuery->where('chinese_name', 'like', $like);
                    })
                    ->orWhereHas('productListerEmployee', function ($employeeQuery) use ($like) {
                        $employeeQuery->where('name', 'like', $like);
                    });
            });
        }

        if ($productTypeId) {
            $skuQuery->where('product_type_id', $productTypeId);
        }

        $typeQuery = ProductType::query()
            ->withCount('skuMatches')
            ->withCount([
                'processingCraft as active_processing_config_count',
                'processingCraft as all_processing_config_count' => function ($query) {
                    $query->withTrashed();
                },
            ])
            ->orderBy('chinese_name');

        if ($search !== '') {
            $typeQuery->where('chinese_name', 'like', '%'.$search.'%');
        }

        return view('business.sku-product-types.index', [
            'activeTab' => $request->query('tab') === 'types' ? 'types' : 'skus',
            'search' => $search,
            'selectedProductTypeId' => $productTypeId,
            'skuMatches' => $skuQuery
                ->paginate(50, ['*'], 'sku_page')
                ->withQueryString(),
            'productTypes' => $typeQuery
                ->paginate(50, ['*'], 'type_page')
                ->withQueryString(),
            'typeOptions' => ProductType::query()
                ->orderBy('chinese_name')
                ->get(['id', 'chinese_name']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', SkuMatchProductType::class);

        return view('business.sku-product-types.form', [
            'entity' => 'sku',
            'skuMatch' => new SkuMatchProductType(),
            'productTypes' => $this->productTypeOptions(),
            'eligibleListers' => $this->eligibleListers(),
        ]);
    }

    public function store(StoreSkuMatchProductTypeRequest $request)
    {
        $this->authorize('create', SkuMatchProductType::class);

        $data = $this->snapshotData($request->validated());
        SkuMatchProductType::create($data);

        return redirect()
            ->route('sku-product-types.index')
            ->with('success', 'SKU 映射已创建。');
    }

    public function edit(SkuMatchProductType $skuProductType)
    {
        $this->authorize('update', $skuProductType);

        return view('business.sku-product-types.form', [
            'entity' => 'sku',
            'skuMatch' => $skuProductType,
            'productTypes' => $this->productTypeOptions(),
            'eligibleListers' => $this->eligibleListers(),
        ]);
    }

    public function update(
        UpdateSkuMatchProductTypeRequest $request,
        SkuMatchProductType $skuProductType
    ) {
        $this->authorize('update', $skuProductType);

        $skuProductType->update($this->snapshotData($request->validated()));

        return redirect()
            ->route('sku-product-types.index')
            ->with('success', 'SKU 映射已更新。');
    }

    public function destroy(SkuMatchProductType $skuProductType)
    {
        $this->authorize('delete', $skuProductType);

        $skuProductType->delete();

        return redirect()
            ->route('sku-product-types.index')
            ->with('success', 'SKU 映射已删除。');
    }

    private function snapshotData(array $validated)
    {
        $productType = ProductType::query()->findOrFail($validated['product_type_id']);
        $lister = null;

        if (!empty($validated['product_lister_employee_id'])) {
            $lister = Employee::query()->findOrFail($validated['product_lister_employee_id']);
        }

        return [
            'original_sku' => $validated['original_sku'],
            'cleaned_sku' => $validated['cleaned_sku'],
            'product_type_id' => $productType->id,
            'chinese_name' => $productType->chinese_name,
            'product_lister_employee_id' => $lister ? $lister->id : null,
            'product_lister' => $lister ? $lister->name : null,
        ];
    }

    private function productTypeOptions()
    {
        return ProductType::query()
            ->orderBy('chinese_name')
            ->get(['id', 'chinese_name']);
    }

    private function eligibleListers()
    {
        return Employee::query()
            ->where('is_active', true)
            ->whereHas('positions', function ($query) {
                $query
                    ->where('is_active', true)
                    ->whereIn('code', ['advertising', 'operations']);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
