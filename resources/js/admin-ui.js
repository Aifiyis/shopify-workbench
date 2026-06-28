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

function initializeAdminUI() {
    initializeSearchableSelects(document);
    initializeDeleteDialog();
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
