<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Shopify Workbench</title>
    @include('reports._styles')
</head>
<body>
    <div class="navbar">
        <h1>Reports - {{ $store->shop_name }}</h1>
        <div class="navbar-actions">
            <a href="{{ route('dashboard.index') }}">Back to stores</a>
        </div>
    </div>

    <div class="container">
        <div class="toolbar">
            <div></div>
            <a class="btn btn-primary" href="{{ route('reports.create', ['store_id' => $store->id]) }}">Create new report</a>
        </div>

        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif

        <div class="panel">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Report name</th>
                            <th>Report description</th>
                            <th>Created on</th>
                            <th>Scheduled reports</th>
                            <th>Actions</th>
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
                                        <span class="badge badge-info">Active: {{ $report['scheduled']['active'] }}</span>
                                        <span class="badge badge-info">Inactive: {{ $report['scheduled']['inactive'] }}</span>
                                    @else
                                        <span class="badge badge-muted">None</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="btn btn-primary btn-small" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id]) }}">Schedule | Run</a>
                                    <a class="btn btn-success btn-small" href="{{ route('reports.edit', ['report' => $report['slug'], 'store_id' => $store->id]) }}">Edit report</a>
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
