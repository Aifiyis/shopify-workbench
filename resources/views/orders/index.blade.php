<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>订单 - 千兴工作台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
        }

        .navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            font-size: 20px;
            color: #333;
        }

        .navbar a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 150px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .table-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 14px;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div>
            <h1>订单 - {{ $store->shop_name }}</h1>
        </div>
        <a href="{{ route('dashboard.index') }}">返回工作台</a>
    </div>

    <div class="container">
        <div class="filter-section">
            <form id="filter-form" method="GET" action="{{ route('orders.index') }}">
                <input type="hidden" name="store_id" value="{{ $store->id }}">

                <div class="filter-row">
                    <div class="form-group">
                        <label>开始日期</label>
                        <input type="date" name="start_date" value="{{ $startDate }}">
                    </div>

                    <div class="form-group">
                        <label>结束日期</label>
                        <input type="date" name="end_date" value="{{ $endDate }}">
                    </div>

                    <button type="submit" class="btn btn-primary">筛选</button>
                    <button type="button" class="btn btn-success" onclick="refreshOrders()">刷新</button>
                    <button type="button" class="btn btn-primary" onclick="exportOrders()">导出 Excel</button>
                </div>
            </form>
        </div>

        <div id="message-container"></div>

        <div class="loading" id="loading">
            <span class="spinner"></span>
            <span>处理中...</span>
        </div>

        <div class="table-section">
            @if ($orders->isEmpty())
                <div class="no-data">
                    <p>暂无订单，请尝试刷新数据。</p>
                </div>
            @else
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>订单日期</th>
                                <th>订单名称</th>
                                <th>产品标题</th>
                                <th>产品类型</th>
                                <th>多类型</th>
                                <th>数量</th>
                                <th>SKU</th>
                                <th>选项 1</th>
                                <th>选项 3</th>
                                <th>产品标签</th>
                                <th>图片名称</th>
                                <th>附加详情</th>
                                <th>自定义文本</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @foreach ($order->lineItems as $lineItem)
                                    <tr>
                                        <td>{{ $order->order_date ? $order->order_date->format('Y-m-d H:i') : '-' }}</td>
                                        <td>{{ $order->order_name }}</td>
                                        <td>{{ $lineItem->product_title }}</td>
                                        <td>{{ $lineItem->product_type }}</td>
                                        <td>{{ $lineItem->multi_types }}</td>
                                        <td>{{ $lineItem->quantity }}</td>
                                        <td>{{ $lineItem->sku }}</td>
                                        <td>{{ $lineItem->option1 }}</td>
                                        <td>{{ $lineItem->option3 }}</td>
                                        <td>{{ $lineItem->product_tags }}</td>
                                        <td>{{ $lineItem->pic_name }}</td>
                                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">{{ $lineItem->extra_details }}</td>
                                        <td>{{ $lineItem->custom_text }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const storeId = {{ $store->id }};

        function showMessage(message, type = 'info') {
            const container = document.getElementById('message-container');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        function showLoading(show = true) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        function refreshOrders() {
            showLoading(true);

            fetch('{{ route("orders.refresh") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ store_id: storeId }),
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage(`${data.message}（共 ${data.count} 个订单）`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage(`请求失败：${error.message}`, 'error');
            });
        }

        function exportOrders() {
            const form = document.getElementById('filter-form');
            const startDate = form.querySelector('input[name="start_date"]').value;
            const endDate = form.querySelector('input[name="end_date"]').value;

            showLoading(true);

            fetch('{{ route("orders.export") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    store_id: storeId,
                    start_date: startDate,
                    end_date: endDate,
                }),
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage('导出成功，正在下载...', 'success');
                    window.location.href = data.download_url;
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage(`请求失败：${error.message}`, 'error');
            });
        }
    </script>
</body>
</html>
