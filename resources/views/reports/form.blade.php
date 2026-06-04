<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $mode === 'create' ? 'Create Report' : 'Edit Report' }} - Shopify Workbench</title>
    @include('reports._styles')
</head>
<body>
    <div class="navbar">
        <h1>{{ $mode === 'create' ? 'Create Report' : 'Edit Report' }} - {{ $store->shop_name }}</h1>
        <div class="navbar-actions">
            <a href="{{ route('reports.index', ['store_id' => $store->id]) }}">Back to reports</a>
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
                            <label for="report_name">Report name</label>
                            <input id="report_name" type="text" name="name" value="{{ $report['name'] }}" placeholder="factory">
                        </div>

                        <div class="form-row">
                            <label for="report_description">Report description</label>
                            <textarea id="report_description" name="description">{{ $report['description'] }}</textarea>
                        </div>

                        <div class="form-row">
                            <label for="field_search">Search fields</label>
                            <input id="field_search" type="text" placeholder="Search order or product fields">
                        </div>

                        @foreach ($fieldGroups as $groupName => $fields)
                            <section class="fields-section" data-section>
                                <div class="fields-title">{{ $groupName }}</div>
                                <div class="fields-grid">
                                    @foreach ($fields as $field)
                                        @php
                                            $fieldId = 'field_' . md5($groupName . $field);
                                        @endphp
                                        <label class="field-option" data-field-option data-field-name="{{ strtolower($field) }}">
                                            <input id="{{ $fieldId }}" type="checkbox" value="{{ $field }}" data-field-checkbox
                                                {{ in_array($field, $report['fields'], true) ? 'checked' : '' }}>
                                            <span>{{ $field }}</span>
                                            <span class="info-dot" title="{{ $field }}">i</span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <aside class="selected-panel">
                        <div class="selected-header">
                            <div class="label" style="margin-bottom: 4px;">Selected fields</div>
                            <div style="color: #62717f; font-size: 13px;">Use the buttons to set table column order.</div>
                        </div>
                        <ul id="selected_fields" class="selected-list"></ul>
                        <div id="selected_hidden_inputs"></div>
                    </aside>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit">Save report</button>
                    <a class="btn btn-light" href="{{ route('reports.index', ['store_id' => $store->id]) }}">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        const selectedFields = @json(array_values($report['fields']));
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
                item.draggable = true;
                item.dataset.index = index;
                item.innerHTML = `
                    <span class="field-name">${field}</span>
                    <span class="sort-actions">
                        <button class="btn btn-light btn-small" type="button" data-move="up" data-index="${index}">Up</button>
                        <button class="btn btn-light btn-small" type="button" data-move="down" data-index="${index}">Down</button>
                        <button class="btn btn-light btn-small" type="button" data-remove="${index}">Remove</button>
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
