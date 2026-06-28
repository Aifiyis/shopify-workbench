@php
    $fieldLabels = [
        'Net Line Amount Estimate' => '净行金额预估',
        'Line Amount' => '行金额',
        'Line Number' => '行号',
        'Gift Card Amount Estimate' => '礼品卡金额预估',
        'Order Metafield 1 Title' => '订单元字段 1 标题',
        'Order Metafield 1 Value' => '订单元字段 1 值',
        'Order Metafield 2 Title' => '订单元字段 2 标题',
        'Order Metafield 2 Value' => '订单元字段 2 值',
        'Order Metafield 3 Title' => '订单元字段 3 标题',
        'Order Metafield 3 Value' => '订单元字段 3 值',
        'Order Metafield 4 Title' => '订单元字段 4 标题',
        'Order Metafield 4 Value' => '订单元字段 4 值',
        'Order Number' => '订单编号',
        'Order Date' => '订单日期',
        'Order Close Date' => '订单关闭日期',
        'Order Updated Date' => '订单更新时间',
        'Order Cancelled Date' => '订单取消日期',
        'Email' => '邮箱',
        'Order Day' => '订单日期（日）',
        'Order Hour' => '订单时间（小时）',
        'Order Month' => '订单月份',
        'Order ID' => '订单 ID',
        'Order Name' => '订单名称',
        'Order Number Sequence' => '订单编号序列',
        'Order Processed Date' => '订单处理日期',
        'Order Line item Properties 1 Title' => '订单行项目属性 1 标题',
        'Order Line item Properties 1 Value' => '订单行项目属性 1 值',
        'Order Line item Properties 2 Title' => '订单行项目属性 2 标题',
        'Order Line item Properties 2 Value' => '订单行项目属性 2 值',
        'Order Line item Properties 3 Title' => '订单行项目属性 3 标题',
        'Order Line item Properties 3 Value' => '订单行项目属性 3 值',
        'Order Line item Properties 4 Title' => '订单行项目属性 4 标题',
        'Order Line item Properties 4 Value' => '订单行项目属性 4 值',
        'Delivery Date' => '配送日期',
        'Product Title' => '产品标题',
        'Variant Title' => '变体标题',
        'Product Name' => '产品名称',
        'Product Price' => '产品价格',
        'Product cost' => '产品成本',
        'Quantity' => '数量',
        'COGS' => '销售成本（COGS）',
        'SKU' => 'SKU',
        'Grams' => '克重',
        'Line Item ID' => '行项目 ID',
        'Product ID' => '产品 ID',
        'Variant ID' => '变体 ID',
        'Vendor' => '供应商',
        'Product Type' => '产品类型',
        'Net Quantity' => '净数量',
        'Product Description' => '产品说明',
        'Barcode' => '条形码',
        'Body HTML' => '正文 HTML',
        'Color' => '颜色',
        'Compare At price' => '原价',
        'Product Created Date' => '产品创建日期',
        'Custom Collections' => '自定义产品系列',
        'Smart Collections' => '智能产品系列',
        'Deleted SKU' => '已删除 SKU',
        'Deleted Variant' => '已删除变体',
        'Handle' => '句柄',
        'Image Contents' => '图片内容',
        'Image Url' => '图片 URL',
        'Inventory Management' => '库存管理',
        'Inventory Policy' => '库存策略',
        'Inventory Quantity' => '库存数量',
        'Option 1' => '选项 1',
        'Option 2' => '选项 2',
        'Option 3' => '选项 3',
        'Product Published Date' => '产品发布日期',
        'Published Scope' => '发布范围',
        'Product Size' => '产品尺寸',
        'Style' => '款式',
        'Order Status' => '订单状态',
        'Product Tags' => '产品标签',
        'Template Suffix' => '模板后缀',
        'URL' => 'URL',
        'Weight' => '重量',
        'Weight Unit' => '重量单位',
        'Product Metafield 1 Title' => '产品元字段 1 标题',
        'Product Metafield 1 Value' => '产品元字段 1 值',
    ];
    $groupLabels = ['Order' => '订单', 'Product' => '产品'];
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $mode === 'create' ? '新增报表' : '编辑报表' }} - 千兴工作台</title>
    @include('reports._styles')
</head>
<body>
    <div class="navbar">
        <h1>{{ $mode === 'create' ? '新增报表' : '编辑报表' }} - {{ $store->shop_name }}</h1>
        <div class="navbar-actions">
            <a href="{{ route('reports.index', ['store_id' => $store->id]) }}">返回报表</a>
        </div>
    </div>

    <div class="container">
        <form method="POST" action="{{ $mode === 'create'
            ? route('reports.store', ['store_id' => $store->id])
            : route('reports.update', ['report' => $report['slug'], 'store_id' => $store->id]) }}">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="panel panel-pad">
                <div class="form-grid">
                    <div>
                        <div class="form-row">
                            <label for="report_name">报表名称</label>
                            <input id="report_name" type="text" name="name" value="{{ $report['name'] }}" placeholder="例如：factory">
                        </div>

                        <div class="form-row">
                            <label for="report_description">报表说明</label>
                            <textarea id="report_description" name="description">{{ $report['description'] }}</textarea>
                        </div>

                        <div class="form-row">
                            <label for="field_search">搜索字段</label>
                            <input id="field_search" type="text" placeholder="搜索订单或产品字段">
                        </div>

                        @foreach ($fieldGroups as $groupName => $fields)
                            <section class="fields-section" data-section>
                                <div class="fields-title">{{ $groupLabels[$groupName] ?? $groupName }}</div>
                                <div class="fields-grid">
                                    @foreach ($fields as $field)
                                        @php
                                            $fieldId = 'field_' . md5($groupName . $field);
                                        @endphp
                                        <label class="field-option" data-field-option data-field-name="{{ strtolower($field) }} {{ $fieldLabels[$field] ?? $field }}">
                                            <input id="{{ $fieldId }}" type="checkbox" value="{{ $field }}" data-field-checkbox
                                                {{ in_array($field, $report['fields'], true) ? 'checked' : '' }}>
                                            <span>{{ $fieldLabels[$field] ?? $field }}</span>
                                            <span class="info-dot" title="{{ $fieldLabels[$field] ?? $field }}">i</span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <aside class="selected-panel">
                        <div class="selected-header">
                            <div class="label" style="margin-bottom: 4px;">已选字段</div>
                        </div>
                        <ul id="selected_fields" class="selected-list"></ul>
                        <div id="selected_hidden_inputs"></div>
                    </aside>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit">保存</button>
                    <a class="btn btn-light" href="{{ route('reports.index', ['store_id' => $store->id]) }}">取消</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        const selectedFields = @json(array_values($report['fields']));
        const fieldLabels = @json($fieldLabels);
        const selectedList = document.getElementById('selected_fields');
        const hiddenInputs = document.getElementById('selected_hidden_inputs');
        const checkboxes = Array.from(document.querySelectorAll('[data-field-checkbox]'));

        function syncCheckboxes() {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectedFields.includes(checkbox.value);
            });
        }

        function renderSelectedFields() {
            selectedList.innerHTML = '';
            hiddenInputs.innerHTML = '';

            selectedFields.forEach((field, index) => {
                const item = document.createElement('li');
                const fieldLabel = fieldLabels[field] || field;
                item.draggable = true;
                item.dataset.index = index;
                item.innerHTML = `
                    <span class="field-name">${fieldLabel}</span>
                    <span class="sort-actions">
                        <button class="btn btn-light btn-small" type="button" data-move="up" data-index="${index}">上移</button>
                        <button class="btn btn-light btn-small" type="button" data-move="down" data-index="${index}">下移</button>
                        <button class="btn btn-light btn-small" type="button" data-remove="${index}">删除</button>
                    </span>
                `;
                selectedList.appendChild(item);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_fields[]';
                input.value = field;
                hiddenInputs.appendChild(input);
            });

            syncCheckboxes();
        }

        function moveField(fromIndex, toIndex) {
            if (toIndex < 0 || toIndex >= selectedFields.length) {
                return;
            }

            const [field] = selectedFields.splice(fromIndex, 1);
            selectedFields.splice(toIndex, 0, field);
            renderSelectedFields();
        }

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const index = selectedFields.indexOf(checkbox.value);

                if (checkbox.checked && index === -1) {
                    selectedFields.push(checkbox.value);
                }

                if (!checkbox.checked && index !== -1) {
                    selectedFields.splice(index, 1);
                }

                renderSelectedFields();
            });
        });

        selectedList.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) {
                return;
            }

            if (button.dataset.move === 'up') {
                moveField(Number(button.dataset.index), Number(button.dataset.index) - 1);
            }

            if (button.dataset.move === 'down') {
                moveField(Number(button.dataset.index), Number(button.dataset.index) + 1);
            }

            if (button.dataset.remove) {
                selectedFields.splice(Number(button.dataset.remove), 1);
                renderSelectedFields();
            }
        });

        let dragIndex = null;

        selectedList.addEventListener('dragstart', (event) => {
            const item = event.target.closest('li');
            if (!item) {
                return;
            }

            dragIndex = Number(item.dataset.index);
            event.dataTransfer.effectAllowed = 'move';
        });

        selectedList.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        selectedList.addEventListener('drop', (event) => {
            event.preventDefault();
            const item = event.target.closest('li');
            if (!item || dragIndex === null) {
                return;
            }

            moveField(dragIndex, Number(item.dataset.index));
            dragIndex = null;
        });

        document.getElementById('field_search').addEventListener('input', (event) => {
            const term = event.target.value.trim().toLowerCase();

            document.querySelectorAll('[data-section]').forEach((section) => {
                let visibleCount = 0;
                section.querySelectorAll('[data-field-option]').forEach((option) => {
                    const visible = option.dataset.fieldName.includes(term);
                    option.style.display = visible ? 'flex' : 'none';
                    if (visible) {
                        visibleCount += 1;
                    }
                });
                section.style.display = visibleCount ? 'block' : 'none';
            });
        });

        renderSelectedFields();
    </script>
</body>
</html>
