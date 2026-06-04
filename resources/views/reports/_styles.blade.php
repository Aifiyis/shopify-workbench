<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: #eef1f4;
        color: #0f2233;
    }

    .navbar {
        background: #ffffff;
        padding: 15px 30px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }

    .navbar h1 {
        font-size: 20px;
        color: #222;
        font-weight: 700;
    }

    .navbar-actions {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .navbar a,
    .link {
        color: #1f6fb2;
        text-decoration: none;
        font-weight: 600;
    }

    .container {
        max-width: 1280px;
        margin: 28px auto;
        padding: 0 20px;
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 12px;
    }

    .panel {
        background: #ffffff;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        overflow: hidden;
    }

    .panel-pad {
        padding: 22px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 30px;
        padding: 7px 13px;
        border: 1px solid transparent;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary {
        background: #2f7fbd;
        color: #ffffff;
        border-color: #286da2;
    }

    .btn-success {
        background: #50b957;
        color: #ffffff;
        border-color: #42a149;
    }

    .btn-danger {
        background: #d9534f;
        color: #ffffff;
        border-color: #c64642;
    }

    .btn-light {
        background: #f7f8fa;
        color: #1f6fb2;
        border-color: #d8dde3;
    }

    .btn-small {
        min-height: 26px;
        padding: 5px 8px;
        font-size: 12px;
    }

    .table-wrap {
        overflow-x: auto;
        padding: 28px 20px 52px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: #ffffff;
    }

    th {
        background: #f7f7f7;
        border-top: 1px solid #d7d7d7;
        border-bottom: 1px solid #d7d7d7;
        color: #162536;
        font-size: 16px;
        font-weight: 700;
        padding: 16px 10px;
        text-align: left;
        white-space: nowrap;
    }

    td {
        border-bottom: 1px solid #dedede;
        color: #092033;
        font-size: 16px;
        padding: 14px 10px;
        vertical-align: middle;
    }

    tbody tr:nth-child(even) {
        background: #f8f8f8;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 3px 9px;
        border-radius: 4px;
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    .badge-info {
        background: #5bc0de;
    }

    .badge-muted {
        background: #777777;
    }

    .status-message {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 5px;
        color: #155724;
        margin-bottom: 16px;
        padding: 12px 14px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(300px, 380px);
        gap: 22px;
    }

    .form-row {
        margin-bottom: 18px;
    }

    label,
    .label {
        display: block;
        color: #172636;
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 9px;
    }

    input[type="text"],
    textarea,
    select {
        width: 100%;
        min-height: 42px;
        padding: 9px 12px;
        border: 1px solid #cfd5da;
        border-radius: 4px;
        color: #172636;
        font-size: 15px;
        background: #ffffff;
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.04);
    }

    textarea {
        min-height: 84px;
        resize: vertical;
    }

    .fields-section {
        border: 1px solid #e2e5e8;
        border-radius: 4px;
        margin-bottom: 16px;
        overflow: hidden;
    }

    .fields-title {
        background: #f3f5f7;
        border-bottom: 1px solid #e2e5e8;
        font-size: 18px;
        padding: 13px 16px;
    }

    .fields-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(240px, 1fr));
        column-gap: 34px;
        row-gap: 0;
        padding: 10px 28px 4px;
    }

    .field-option {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 40px;
        padding: 8px 0;
        font-size: 17px;
    }

    .field-option input {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
    }

    .info-dot {
        align-items: center;
        background: #d8dadd;
        border-radius: 50%;
        color: #ffffff;
        display: inline-flex;
        font-size: 12px;
        font-weight: 700;
        height: 18px;
        justify-content: center;
        width: 18px;
    }

    .selected-panel {
        border: 1px solid #d8dde3;
        border-radius: 4px;
        position: sticky;
        top: 16px;
        overflow: hidden;
    }

    .selected-header {
        background: #f7f8fa;
        border-bottom: 1px solid #d8dde3;
        padding: 12px 14px;
    }

    .selected-list {
        list-style: none;
        max-height: 520px;
        overflow-y: auto;
        padding: 8px;
    }

    .selected-list li {
        align-items: center;
        background: #ffffff;
        border: 1px solid #dde2e7;
        border-radius: 4px;
        display: flex;
        gap: 8px;
        justify-content: space-between;
        margin-bottom: 7px;
        min-height: 38px;
        padding: 6px 8px;
    }

    .field-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sort-actions {
        display: flex;
        gap: 4px;
        flex: 0 0 auto;
    }

    .tabs {
        align-items: flex-end;
        display: flex;
        gap: 0;
        margin: 0 0 -1px 18px;
    }

    .tab {
        background: transparent;
        border: 1px solid transparent;
        border-top: 3px solid transparent;
        color: #1f6fb2;
        min-width: 150px;
        padding: 16px 18px 15px;
        text-align: center;
        text-decoration: none;
    }

    .tab.active {
        background: #ffffff;
        border-color: #d8dde3;
        border-top-color: #008000;
        border-bottom-color: #ffffff;
        color: #26323d;
    }

    .schedule-header {
        align-items: center;
        border-bottom: 1px solid #e5e8eb;
        display: flex;
        gap: 24px;
        padding: 28px 18px;
    }

    .pill-help {
        background: #ff881c;
        border-radius: 18px;
        color: #ffffff;
        font-size: 13px;
        font-weight: 700;
        padding: 10px 14px;
    }

    .schedule-form {
        padding: 24px 18px;
        max-width: 940px;
    }

    .inline-row {
        align-items: center;
        display: grid;
        gap: 24px;
        grid-template-columns: minmax(260px, 440px) auto auto;
    }

    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 48px;
    }

    .pagination span {
        border: 1px solid #d8dde3;
        color: #1f6fb2;
        min-width: 34px;
        padding: 9px 12px;
        text-align: center;
    }

    .pagination .active {
        background: #2f7fbd;
        color: #ffffff;
    }

    @media (max-width: 900px) {
        .form-grid,
        .fields-grid,
        .inline-row {
            grid-template-columns: 1fr;
        }

        .selected-panel {
            position: static;
        }

        .tabs {
            margin-left: 0;
            overflow-x: auto;
        }

        .tab {
            min-width: 130px;
        }

        .navbar,
        .toolbar {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
