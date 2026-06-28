@extends('layouts.app')

@section('content')
    @php
        $editing = $configuration->exists;
        $action = $editing
            ? route('order-processing.update', $configuration)
            : route('order-processing.store');
        $selectedOrderEmployees = collect(old(
            'order_processor_employee_ids',
            $editing ? $configuration->orderProcessorEmployees->pluck('id')->all() : []
        ))->map(function ($id) {
            return (string) $id;
        })->all();
        $selectedArtworkEmployees = collect(old(
            'artwork_processor_employee_ids',
            $editing ? $configuration->artworkProcessorEmployees->pluck('id')->all() : []
        ))->map(function ($id) {
            return (string) $id;
        })->all();
        $selectedProcurementEmployees = collect(old(
            'procurement_processor_employee_ids',
            $editing ? $configuration->procurementProcessorEmployees->pluck('id')->all() : []
        ))->map(function ($id) {
            return (string) $id;
        })->all();
        $employeeLabel = function ($employee) {
            if ($employee->trashed()) {
                return $employee->name.'（已删除）';
            }
            if (!$employee->is_active) {
                return $employee->name.'（已停用）';
            }
            return $employee->name;
        };
        $settlement = old('settlement_method', $configuration->settlement_method);
        $settlementOptions = collect(['月结', '周结'])->when(
            $settlement && !in_array($settlement, ['月结', '周结'], true),
            function ($options) use ($settlement) {
                return $options->push($settlement);
            }
        );
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <h1 class="m-0">{{ $editing ? '编辑订单处理配置' : '新增订单处理配置' }}</h1>
        <a class="button button-secondary no-underline" href="{{ route('order-processing.index') }}">返回列表</a>
    </div>

    <form method="POST" action="{{ $action }}" class="mt-5 max-w-4xl space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="product_type_id" class="mb-1 block font-semibold">产品类型</label>
                <select id="product_type_id" name="product_type_id" required data-searchable-select data-placeholder="请选择产品类型">
                    <option value="">请选择产品类型</option>
                    @foreach ($productTypes as $productType)
                        <option value="{{ $productType->id }}" @if ((string) old('product_type_id', $configuration->product_type_id) === (string) $productType->id) selected @endif>
                            {{ $productType->chinese_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="craft_id" class="mb-1 block font-semibold">工艺</label>
                <select id="craft_id" name="craft_id" data-searchable-select data-option-type="craft" data-placeholder="可不选择">
                    <option value="">不选择</option>
                    @foreach ($crafts as $craft)
                        <option value="{{ $craft->id }}"
                                data-depth="{{ substr_count($craft->path, '-') }}"
                                data-path="{{ $craft->path }}"
                                @if ((string) old('craft_id', $configuration->craft_id) === (string) $craft->id) selected @endif>
                            {{ $craft->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="order_processor_employee_ids" class="mb-1 block font-semibold">订单处理人</label>
            <select id="order_processor_employee_ids" name="order_processor_employee_ids[]" multiple data-searchable-select data-placeholder="可不选择">
                @foreach ($orderEmployees as $employee)
                    <option value="{{ $employee->id }}" @if (in_array((string) $employee->id, $selectedOrderEmployees, true)) selected @endif>
                        {{ $employeeLabel($employee) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="artwork_processor_employee_ids" class="mb-1 block font-semibold">图画处理人</label>
            <select id="artwork_processor_employee_ids" name="artwork_processor_employee_ids[]" multiple data-searchable-select data-placeholder="可不选择">
                @foreach ($artworkEmployees as $employee)
                    <option value="{{ $employee->id }}" @if (in_array((string) $employee->id, $selectedArtworkEmployees, true)) selected @endif>
                        {{ $employeeLabel($employee) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="procurement_processor_employee_ids" class="mb-1 block font-semibold">采购处理人</label>
            <select id="procurement_processor_employee_ids" name="procurement_processor_employee_ids[]" multiple data-searchable-select data-placeholder="可不选择">
                @foreach ($procurementEmployees as $employee)
                    <option value="{{ $employee->id }}" @if (in_array((string) $employee->id, $selectedProcurementEmployees, true)) selected @endif>
                        {{ $employeeLabel($employee) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="settlement_method" class="mb-1 block font-semibold">结算方式</label>
                <select id="settlement_method" name="settlement_method" data-searchable-select data-create-enabled="true" data-placeholder="可不选择">
                    <option value="">不选择</option>
                    @foreach ($settlementOptions as $option)
                        <option value="{{ $option }}" @if ($settlement === $option) selected @endif>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="spreadsheet_template" class="mb-1 block font-semibold">表格模板</label>
                <input id="spreadsheet_template" name="spreadsheet_template" maxlength="255" value="{{ old('spreadsheet_template', $configuration->spreadsheet_template) }}">
            </div>
        </div>

        <div>
            <label for="spreadsheet_template_description" class="mb-1 block font-semibold">模板说明</label>
            <textarea id="spreadsheet_template_description" name="spreadsheet_template_description" rows="4">{{ old('spreadsheet_template_description', $configuration->spreadsheet_template_description) }}</textarea>
        </div>

        <div class="flex gap-2 border-t border-gray-200 pt-4">
            <button type="submit" class="button bg-green-700 text-white hover:bg-green-800">{{ $editing ? '保存修改' : '创建配置' }}</button>
            <a class="button button-secondary no-underline" href="{{ route('order-processing.index') }}">取消</a>
        </div>
    </form>
@endsection
