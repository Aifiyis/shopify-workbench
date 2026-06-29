<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateSkuMatchProductTypeRequest;
use App\Http\Requests\StoreSkuMatchProductTypeRequest;
use App\Http\Requests\UpdateSkuMatchProductTypeRequest;
use App\Models\Employee;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use App\Services\SkuCleaningService;
use App\Support\PerPageOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkuMatchProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SkuMatchProductType::class);
        $this->authorize('viewAny', ProductType::class);

        $search = trim((string) $request->query('search', ''));
        $productTypeId = $request->query('product_type_id');
        $skuPerPage = PerPageOptions::resolve($request, 'sku_per_page');
        $typePerPage = PerPageOptions::resolve($request, 'type_per_page');

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
                ->paginate($skuPerPage, ['*'], 'sku_page')
                ->withQueryString(),
            'productTypes' => $typeQuery
                ->paginate($typePerPage, ['*'], 'type_page')
                ->withQueryString(),
            'typeOptions' => ProductType::query()
                ->orderBy('chinese_name')
                ->get(['id', 'chinese_name']),
            'eligibleListers' => $this->eligibleListers(),
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

    public function bulkUpdate(BulkUpdateSkuMatchProductTypeRequest $request)
    {
        $validated = $request->validated();
        $skuMatches = SkuMatchProductType::query()
            ->whereIn('id', $validated['sku_ids'])
            ->get();

        if ($skuMatches->count() !== count($validated['sku_ids'])) {
            throw ValidationException::withMessages([
                'sku_ids' => '部分 SKU 映射不存在或已删除，请刷新页面后重试。',
            ]);
        }

        foreach ($skuMatches as $skuMatch) {
            $this->authorize('update', $skuMatch);
        }

        if ($skuMatches->pluck('cleaned_sku')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'sku_ids' => '只能批量修改清洗后 SKU 完全相同的记录。',
            ]);
        }

        $snapshot = $this->assignmentSnapshotData(
            $validated['product_type_id'],
            $validated['product_lister_employee_id'] ?? null
        );

        DB::transaction(function () use ($skuMatches, $snapshot) {
            SkuMatchProductType::query()
                ->whereIn('id', $skuMatches->pluck('id'))
                ->update($snapshot);
        });

        $returnQuery = collect($validated['return_query'] ?? [])
            ->reject(function ($value) {
                return $value === null || $value === '';
            })
            ->all();

        return redirect()
            ->route('sku-product-types.index', $returnQuery)
            ->with('success', '已批量更新 '.$skuMatches->count().' 条 SKU 映射。');
    }

    public function cleanSku(Request $request, SkuCleaningService $cleaner)
    {
        $this->authorize('create', SkuMatchProductType::class);
        $request->merge([
            'original_sku' => trim((string) $request->input('original_sku')),
        ]);
        $validated = $request->validate([
            'original_sku' => ['required', 'string', 'max:255'],
        ], [
            'original_sku.required' => '请输入原始 SKU。',
        ]);

        return response()->json([
            'cleaned_sku' => $cleaner->cleanSkuUsingValuesAndPatterns(
                $validated['original_sku']
            ),
        ]);
    }

    private function snapshotData(array $validated)
    {
        return array_merge([
            'original_sku' => $validated['original_sku'],
            'cleaned_sku' => $validated['cleaned_sku'],
        ], $this->assignmentSnapshotData(
            $validated['product_type_id'],
            $validated['product_lister_employee_id'] ?? null
        ));
    }

    private function assignmentSnapshotData($productTypeId, $listerEmployeeId)
    {
        $productType = ProductType::query()->findOrFail($productTypeId);
        $lister = $listerEmployeeId
            ? Employee::query()->findOrFail($listerEmployeeId)
            : null;

        return [
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
