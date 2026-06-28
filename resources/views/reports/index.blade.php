<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报表 - 千兴工作台</title>
    @include('reports._styles')
</head>
<body>
    <div class="navbar">
        <h1>报表 - {{ $store->shop_name }}</h1>
        <div class="navbar-actions">
            <a href="{{ route('dashboard.index') }}">返回店铺</a>
        </div>
    </div>

    <div class="container">
        <div class="toolbar">
            <div></div>
            <a class="btn btn-primary" href="{{ route('reports.create', ['store_id' => $store->id]) }}">新增报表</a>
        </div>

        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif

        <div class="panel">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>报表名称</th>
                            <th>报表说明</th>
                            <th>创建时间</th>
                            <th>计划任务</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            <tr>
                                <td>{{ $report['name'] }}</td>
                                <td>{{ $report['description'] }}</td>
                                <td>{{ $report['created_on'] }}</td>
                                <td>
                                    @if ($report['scheduled'])
                                        <span class="badge badge-info">启用：{{ $report['scheduled']['active'] }}</span>
                                        <span class="badge badge-info">停用：{{ $report['scheduled']['inactive'] }}</span>
                                    @else
                                        <span class="badge badge-muted">无</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="btn btn-primary btn-small" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id]) }}">计划与运行</a>
                                    <a class="btn btn-success btn-small" href="{{ route('reports.edit', ['report' => $report['slug'], 'store_id' => $store->id]) }}">编辑报表</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
