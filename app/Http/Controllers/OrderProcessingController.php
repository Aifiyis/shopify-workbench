<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderProcessingRequest;
use App\Http\Requests\UpdateOrderProcessingRequest;
use App\Models\Employee;
use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Support\PerPageOptions;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderProcessingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductProcessingCraft::class);

        $search = trim((string) $request->query('search', ''));
        $productTypeId = $request->query('product_type_id');
        $craftId = $request->query('craft_id');
        $perPage = PerPageOptions::resolve($request, 'per_page');

        $query = ProductProcessingCraft::query()
            ->with([
                'productType' => function ($query) {
                    $query->withTrashed();
                },
                'craft' => function ($query) {
                    $query->withTrashed();
                },
                'orderProcessorEmployees',
                'artworkProcessorEmployees',
                'procurementProcessorEmployees',
            ])
            ->orderBy('chinese_name');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($query) use ($like) {
                $query
                    ->where('chinese_name', 'like', $like)
                    ->orWhere('settlement_method', 'like', $like)
                    ->orWhere('spreadsheet_template', 'like', $like)
                    ->orWhereHas('productType', function ($productTypeQuery) use ($like) {
                        $productTypeQuery->withTrashed()->where('chinese_name', 'like', $like);
                    })
                    ->orWhereHas('craft', function ($craftQuery) use ($like) {
                        $craftQuery
                            ->withTrashed()
                            ->where(function ($craftQuery) use ($like) {
                                $craftQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('path', 'like', $like);
                            });
                    });

                foreach ([
                    'orderProcessorEmployees',
                    'artworkProcessorEmployees',
                    'procurementProcessorEmployees',
                ] as $relation) {
                    $query->orWhereHas($relation, function ($employeeQuery) use ($like) {
                        $employeeQuery->withTrashed()->where('name', 'like', $like);
                    });
                }
            });
        }

        if ($productTypeId) {
            $query->where('product_type_id', $productTypeId);
        }
        if ($craftId) {
            $query->where('craft_id', $craftId);
        }

        return view('business.order-processing.index', [
            'configurations' => $query->paginate($perPage)->withQueryString(),
            'search' => $search,
            'selectedProductTypeId' => $productTypeId,
            'selectedCraftId' => $craftId,
            'productTypes' => ProductType::query()
                ->orderBy('chinese_name')
                ->get(['id', 'chinese_name']),
            'crafts' => ProcessingCraftNode::query()
                ->orderBy('path')
                ->get(['id', 'name', 'path']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', ProductProcessingCraft::class);

        return view('business.order-processing.form', $this->formData(
            new ProductProcessingCraft()
        ));
    }

    public function store(StoreOrderProcessingRequest $request)
    {
        $this->authorize('create', ProductProcessingCraft::class);

        try {
            DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $configuration = ProductProcessingCraft::create(
                    $this->configurationData($validated)
                );
                $this->syncEmployees($configuration, $validated);
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateProductTypeValidation($exception);
        }

        return redirect()
            ->route('order-processing.index')
            ->with('success', '订单处理配置已创建。');
    }

    public function edit(ProductProcessingCraft $orderProcessing)
    {
        $this->authorize('update', $orderProcessing);

        $orderProcessing->load([
            'orderProcessorEmployees',
            'artworkProcessorEmployees',
            'procurementProcessorEmployees',
        ]);

        return view('business.order-processing.form', $this->formData($orderProcessing));
    }

    public function update(
        UpdateOrderProcessingRequest $request,
        ProductProcessingCraft $orderProcessing
    ) {
        $this->authorize('update', $orderProcessing);

        try {
            DB::transaction(function () use ($request, $orderProcessing) {
                $validated = $request->validated();
                $orderProcessing->update($this->configurationData($validated));
                $this->syncEmployees($orderProcessing, $validated);
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateProductTypeValidation($exception);
        }

        return redirect()
            ->route('order-processing.index')
            ->with('success', '订单处理配置已更新。');
    }

    public function destroy(ProductProcessingCraft $orderProcessing)
    {
        $this->authorize('delete', $orderProcessing);

        $orderProcessing->delete();

        return redirect()
            ->route('order-processing.index')
            ->with('success', '订单处理配置已删除。');
    }

    private function formData(ProductProcessingCraft $configuration)
    {
        $configuredTypeIds = ProductProcessingCraft::withTrashed()
            ->when($configuration->exists, function ($query) use ($configuration) {
                $query->where('id', '!=', $configuration->id);
            })
            ->pluck('product_type_id')
            ->filter();

        return [
            'configuration' => $configuration,
            'productTypes' => ProductType::query()
                ->whereNotIn('id', $configuredTypeIds)
                ->orderBy('chinese_name')
                ->get(['id', 'chinese_name']),
            'crafts' => ProcessingCraftNode::query()
                ->orderBy('path')
                ->get(['id', 'name', 'path']),
            'orderEmployees' => $this->employeeOptions(
                'order_processing',
                $configuration,
                'orderProcessorEmployees'
            ),
            'artworkEmployees' => $this->employeeOptions(
                'artwork_processing',
                $configuration,
                'artworkProcessorEmployees'
            ),
            'procurementEmployees' => $this->employeeOptions(
                'procurement',
                $configuration,
                'procurementProcessorEmployees'
            ),
        ];
    }

    private function employeeOptions($positionCode, ProductProcessingCraft $configuration, $relation)
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->whereHas('positions', function ($query) use ($positionCode) {
                $query
                    ->where('code', $positionCode)
                    ->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'deleted_at']);

        if (!$configuration->exists) {
            return $employees;
        }

        $historical = $configuration->{$relation};

        return $employees
            ->concat($historical)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function configurationData(array $validated)
    {
        $productType = ProductType::query()->findOrFail($validated['product_type_id']);

        return [
            'product_type_id' => $productType->id,
            'chinese_name' => $productType->chinese_name,
            'craft_id' => $validated['craft_id'] ?? null,
            'settlement_method' => $validated['settlement_method'] ?? null,
            'spreadsheet_template' => $validated['spreadsheet_template'] ?? null,
            'spreadsheet_template_description' => $validated['spreadsheet_template_description'] ?? null,
        ];
    }

    private function syncEmployees(ProductProcessingCraft $configuration, array $validated)
    {
        foreach ([
            'order_processor_employee_ids' => [
                'relation' => 'orderProcessorEmployees',
                'type' => 'order_processing',
            ],
            'artwork_processor_employee_ids' => [
                'relation' => 'artworkProcessorEmployees',
                'type' => 'artwork_processing',
            ],
            'procurement_processor_employee_ids' => [
                'relation' => 'procurementProcessorEmployees',
                'type' => 'procurement',
            ],
        ] as $field => $assignment) {
            $payload = collect($validated[$field] ?? [])
                ->mapWithKeys(function ($employeeId) use ($assignment) {
                    return [$employeeId => ['assignment_type' => $assignment['type']]];
                })
                ->all();

            $configuration->{$assignment['relation']}()->sync($payload);
        }
    }

    private function throwDuplicateProductTypeValidation(QueryException $exception)
    {
        if ((string) $exception->getCode() === '23000'
            || (int) ($exception->errorInfo[1] ?? 0) === 19) {
            throw ValidationException::withMessages([
                'product_type_id' => '该产品类型已有订单处理配置，包括已删除的记录。',
            ]);
        }

        throw $exception;
    }
}
