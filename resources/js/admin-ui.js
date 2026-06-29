function parseDataOption(option) {
    let data = {};

    if (option.dataset.data) {
        try {
            data = JSON.parse(option.dataset.data);
        } catch (error) {
            data = {};
        }
    }

    const depth = Number(option.dataset.depth || data.depth || 0);
    const path = option.dataset.path || data.path || option.textContent.trim();

    return Object.assign(data, {
        value: option.value,
        text: option.textContent.trim(),
        depth: Number.isFinite(depth) ? depth : 0,
        path: path,
    });
}

function isEnabled(value) {
    return value === '' || value === 'true' || value === '1';
}

function quickCreateMessage(data, field) {
    if (data && data.errors && Array.isArray(data.errors[field]) && data.errors[field].length) {
        return data.errors[field][0];
    }

    return data && data.message ? data.message : '创建失败，请稍后重试。';
}

function showQuickCreateFeedback(select, message, editUrl) {
    const targetSelector = select.dataset.quickCreateErrorTarget;
    if (!targetSelector) {
        return;
    }

    let target = null;
    try {
        target = document.querySelector(targetSelector);
    } catch (error) {
        return;
    }

    if (!target) {
        return;
    }

    target.replaceChildren();
    if (message) {
        target.appendChild(document.createTextNode(message));
    }
    if (editUrl) {
        const link = document.createElement('a');
        link.href = editUrl;
        link.textContent = select.dataset.quickCreateEditLabel || '编辑已有记录';
        link.className = 'ml-2';
        target.appendChild(link);
    }
}

function createRemoteOption(select, input, callback) {
    const url = select.dataset.quickCreateUrl;
    const field = select.dataset.quickCreateField || 'name';
    const valueKey = select.dataset.quickCreateValueKey || 'id';
    const labelKey = select.dataset.quickCreateLabelKey || field;
    const csrf = document.querySelector('meta[name="csrf-token"]');
    const payload = {};
    payload[field] = input.trim();

    showQuickCreateFeedback(select, '', null);

    fetch(url, {
        method: select.dataset.quickCreateMethod || 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : '',
        },
        body: JSON.stringify(payload),
    })
        .then(function (response) {
            return response.json()
                .catch(function () {
                    return {};
                })
                .then(function (data) {
                    return { ok: response.ok, data: data };
                });
        })
        .then(function (result) {
            if (!result.ok) {
                showQuickCreateFeedback(
                    select,
                    quickCreateMessage(result.data, field),
                    result.data.edit_url || null
                );
                callback();
                return;
            }

            const option = Object.assign({}, result.data, {
                value: String(result.data[valueKey]),
                text: result.data[labelKey],
            });
            showQuickCreateFeedback(select, '', null);
            callback(option);
        })
        .catch(function () {
            showQuickCreateFeedback(select, '创建失败，请稍后重试。', null);
            callback();
        });
}

function initializeSelect(select) {
    if (select.dataset.adminUiInitialized === 'true' || typeof window.TomSelect === 'undefined') {
        return;
    }

    const optionData = {};
    let hasCraftOptions = false;

    Array.from(select.options).forEach(function (option) {
        const data = parseDataOption(option);
        optionData[option.value] = data;
        hasCraftOptions = hasCraftOptions || option.hasAttribute('data-depth') || option.hasAttribute('data-path');
        option.dataset.data = JSON.stringify(data);
    });

    const createValue = select.dataset.createEnabled !== undefined
        ? select.dataset.createEnabled
        : select.dataset.create;
    const settings = {
        create: createValue !== undefined && isEnabled(createValue),
        maxItems: select.multiple ? null : 1,
        plugins: select.multiple ? ['remove_button'] : [],
        placeholder: select.dataset.placeholder || undefined,
        allowEmptyOption: true,
    };

    if (select.dataset.quickCreateUrl) {
        settings.create = function (input, callback) {
            createRemoteOption(select, input, callback);
        };
        settings.createFilter = function (input) {
            return input.trim().length > 0;
        };
        settings.persist = false;
    }

    if (hasCraftOptions || select.dataset.optionType === 'craft') {
        settings.searchField = ['text', 'path'];
        settings.render = {
            option: function (data, escape) {
                const source = optionData[data.value] || data;
                const depth = Number(source.depth || 0);
                const path = source.path || source.text;

                return '<div class="craft-select-option" style="--craft-depth: ' + depth + '">' +
                    '<span>' + escape(source.text) + '</span>' +
                    (path !== source.text ? '<small>' + escape(path) + '</small>' : '') +
                    '</div>';
            },
            item: function (data, escape) {
                const source = optionData[data.value] || data;
                return '<div>' + escape(source.path || source.text) + '</div>';
            },
        };
    }

    select.dataset.adminUiInitialized = 'true';
    new window.TomSelect(select, settings);
}

function initializeSearchableSelects(root) {
    const scope = root || document;
    scope.querySelectorAll('[data-searchable-select]').forEach(initializeSelect);
}

function resolveSelect(select) {
    if (typeof select === 'string') {
        return document.querySelector(select);
    }

    return select;
}

function addSelectOption(select, option, choose) {
    const target = resolveSelect(select);
    if (!target || !option) {
        return;
    }

    const nativeSelect = target.tagName === 'SELECT' ? target : target.input;
    const tomSelect = target.tomselect || (nativeSelect && nativeSelect.tomselect) ||
        (typeof target.addOption === 'function' ? target : null);
    const value = String(option.value !== undefined ? option.value : option.id);
    const text = option.text || option.chinese_name || option.name || value;
    const normalized = Object.assign({}, option, {
        value: value,
        text: text,
        path: option.path || text,
        depth: Number(option.depth || 0),
    });

    if (tomSelect) {
        tomSelect.addOption(normalized);
        tomSelect.refreshOptions(false);
        if (choose) {
            tomSelect.addItem(value, true);
        }
        return;
    }

    if (!nativeSelect) {
        return;
    }

    let nativeOption = Array.from(nativeSelect.options).find(function (item) {
        return item.value === value;
    });
    if (!nativeOption) {
        nativeOption = new Option(text, value, false, false);
        nativeSelect.add(nativeOption);
    }
    nativeOption.dataset.depth = String(normalized.depth);
    nativeOption.dataset.path = normalized.path;
    nativeOption.dataset.data = JSON.stringify(normalized);
    if (choose) {
        nativeOption.selected = true;
        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function findDeleteForm(trigger) {
    const reference = trigger.dataset.deleteForm;
    if (!reference) {
        return trigger.closest('form');
    }

    const byId = document.getElementById(reference.replace(/^#/, ''));
    if (byId) {
        return byId;
    }

    try {
        return document.querySelector(reference);
    } catch (error) {
        return null;
    }
}

function initializeDeleteDialog() {
    const dialog = document.getElementById('confirm-delete-dialog');
    if (!dialog || dialog.dataset.adminUiInitialized === 'true') {
        return;
    }

    const title = dialog.querySelector('#confirm-delete-title');
    const message = dialog.querySelector('#confirm-delete-message');
    const cancel = dialog.querySelector('[data-delete-cancel]');
    const confirm = dialog.querySelector('[data-delete-confirm]');
    const defaultTitle = title ? title.textContent : '';
    const defaultMessage = message ? message.textContent : '';
    let targetForm = null;

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-delete-trigger]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        targetForm = findDeleteForm(trigger);
        if (!targetForm) {
            return;
        }

        if (title) {
            title.textContent = trigger.dataset.deleteTitle || defaultTitle;
        }
        if (message) {
            message.textContent = trigger.dataset.deleteMessage || defaultMessage;
        }
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });

    cancel.addEventListener('click', function () {
        targetForm = null;
        dialog.close();
    });

    confirm.addEventListener('click', function () {
        if (!targetForm) {
            dialog.close();
            return;
        }

        const form = targetForm;
        targetForm = null;
        dialog.close();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    dialog.addEventListener('cancel', function () {
        targetForm = null;
    });
    dialog.dataset.adminUiInitialized = 'true';
}

function initializeSkuBulkEditors() {
    document.querySelectorAll('[data-sku-bulk-root]').forEach(function (root) {
        if (root.dataset.adminUiInitialized === 'true') {
            return;
        }

        const checkboxes = Array.from(root.querySelectorAll('[data-sku-bulk-checkbox]'));
        const count = root.querySelector('[data-sku-bulk-count]');
        const feedback = root.querySelector('[data-sku-bulk-feedback]');
        const selectGroup = root.querySelector('[data-sku-bulk-select-group]');
        const open = root.querySelector('[data-sku-bulk-open]');
        const dialog = document.getElementById('sku-bulk-edit-dialog');

        if (!checkboxes.length || !dialog || !open) {
            root.dataset.adminUiInitialized = 'true';
            return;
        }

        const hiddenInputs = dialog.querySelector('[data-sku-bulk-hidden-inputs]');
        const dialogCount = dialog.querySelector('[data-sku-bulk-dialog-count]');
        const dialogGroup = dialog.querySelector('[data-sku-bulk-dialog-group]');
        const cancel = dialog.querySelector('[data-sku-bulk-cancel]');

        function selectedRows() {
            return checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            });
        }

        function updateState(message) {
            const selected = selectedRows();
            const group = selected.length ? selected[0].dataset.cleanedSku : '';

            checkboxes.forEach(function (checkbox) {
                checkbox.disabled = group !== ''
                    && !checkbox.checked
                    && checkbox.dataset.cleanedSku !== group;
            });
            count.textContent = String(selected.length);
            open.disabled = selected.length < 2;
            feedback.textContent = message || '';
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const other = selectedRows().find(function (selected) {
                    return selected !== checkbox;
                });

                if (checkbox.checked && other
                    && other.dataset.cleanedSku !== checkbox.dataset.cleanedSku) {
                    checkbox.checked = false;
                    updateState('只能选择清洗后 SKU 完全相同的记录。');
                    return;
                }

                updateState('');
            });
        });

        selectGroup.addEventListener('click', function () {
            const selected = selectedRows();
            if (!selected.length) {
                updateState('请先勾选一条记录，再全选同组。');
                return;
            }

            const group = selected[0].dataset.cleanedSku;
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = checkbox.dataset.cleanedSku === group;
            });
            updateState('');
        });

        open.addEventListener('click', function () {
            const selected = selectedRows();
            if (selected.length < 2) {
                updateState('请至少选择两条记录。');
                return;
            }

            hiddenInputs.replaceChildren();
            selected.forEach(function (checkbox) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'sku_ids[]';
                input.value = checkbox.value;
                hiddenInputs.appendChild(input);
            });
            dialogCount.textContent = String(selected.length);
            dialogGroup.textContent = selected[0].dataset.cleanedSku;
            initializeSearchableSelects(dialog);
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });

        cancel.addEventListener('click', function () {
            dialog.close();
        });
        dialog.addEventListener('close', function () {
            hiddenInputs.replaceChildren();
        });
        root.dataset.adminUiInitialized = 'true';
        updateState('');
    });
}

function initializeSkuCleaners() {
    document.querySelectorAll('[data-sku-clean-trigger]').forEach(function (trigger) {
        if (trigger.dataset.adminUiInitialized === 'true') {
            return;
        }

        const original = document.getElementById('original_sku');
        const cleaned = document.getElementById('cleaned_sku');
        const feedback = document.querySelector('[data-sku-clean-feedback]');
        const csrf = document.querySelector('meta[name="csrf-token"]');
        const defaultLabel = trigger.textContent;

        trigger.addEventListener('click', function () {
            feedback.textContent = '';
            trigger.disabled = true;
            trigger.textContent = '清洗中...';

            fetch(trigger.dataset.cleanUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.content : '',
                },
                body: JSON.stringify({ original_sku: original.value }),
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        const errors = result.data.errors && result.data.errors.original_sku;
                        feedback.textContent = errors && errors.length
                            ? errors[0]
                            : (result.data.message || 'SKU 清洗失败，请稍后重试。');
                        return;
                    }
                    cleaned.value = result.data.cleaned_sku || '';
                })
                .catch(function () {
                    feedback.textContent = 'SKU 清洗失败，请稍后重试。';
                })
                .finally(function () {
                    trigger.disabled = false;
                    trigger.textContent = defaultLabel;
                });
        });
        trigger.dataset.adminUiInitialized = 'true';
    });
}

function initializeAdminUI() {
    initializeSearchableSelects(document);
    initializeDeleteDialog();
    initializeSkuBulkEditors();
    initializeSkuCleaners();
}

window.AdminUI = Object.assign(window.AdminUI || {}, {
    addSelectOption: addSelectOption,
    initialize: initializeAdminUI,
    initializeSearchableSelects: initializeSearchableSelects,
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAdminUI);
} else {
    initializeAdminUI();
}
