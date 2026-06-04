<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Report - Shopify Workbench</title>
    @include('reports._styles')
</head>
<body>
    <div class="navbar">
        <h1>Schedule - {{ $store->shop_name }}</h1>
        <div class="navbar-actions">
            <a href="{{ route('reports.index', ['store_id' => $store->id]) }}">Back to reports</a>
        </div>
    </div>

    <div class="container">
        <nav class="tabs">
            <a class="tab {{ $tab === 'create' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'create']) }}">New Schedule</a>
            <a class="tab {{ $tab === 'scheduled' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'scheduled']) }}">Scheduled reports</a>
            <a class="tab {{ $tab === 'actual' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'actual']) }}">Actual Schedule</a>
            <a class="tab {{ $tab === 'history' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'history']) }}">Report History</a>
        </nav>

        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif

        <div class="panel">
            @if ($tab === 'create')
                <div class="schedule-header">
                    <h2 style="font-size: 16px;">Scheduling information</h2>
                    <span class="pill-help">Quick tour - How to schedule?</span>
                </div>

                <form method="POST" action="{{ route('reports.schedule.save', ['report' => $report['slug'], 'store_id' => $store->id]) }}" class="schedule-form">
                    @csrf

                    <div class="form-row">
                        <label for="report_to_schedule">Select the report to be scheduled</label>
                        <select id="report_to_schedule" name="report">
                            @foreach ($reports as $availableReport)
                                <option value="{{ $availableReport['slug'] }}" {{ $availableReport['slug'] === $report['slug'] ? 'selected' : '' }}>
                                    {{ $availableReport['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="schedule_name">Report schedule name</label>
                        <input id="schedule_name" type="text" name="schedule_name" value="{{ $report['name'] }}">
                    </div>

                    <div class="form-row">
                        <label for="run_rule">How often do you want to run the report?</label>
                        <select id="run_rule" name="run_rule">
                            <option>Run Immediately</option>
                            <option>Daily</option>
                            <option>Weekly</option>
                            <option>Monthly</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="start_date">Start date</label>
                        <input id="start_date" type="text" name="start_date" value="05/23/2026 12:00:00 AM">
                    </div>

                    <div class="form-row">
                        <label for="end_date">End date</label>
                        <div class="inline-row">
                            <input id="end_date" type="text" name="end_date" value="05/30/2026 11:59:59 PM">
                            <span style="font-weight: 700; color: #63717f;">Or</span>
                            <label style="display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 400;">
                                <input type="checkbox" name="repeat_forever" value="1"> Repeat Forever
                            </label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">Save schedule</button>
                        <a class="btn btn-light" href="{{ route('reports.index', ['store_id' => $store->id]) }}">Cancel</a>
                    </div>
                </form>
            @elseif ($tab === 'scheduled')
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Report name</th>
                                <th>Scheduled reports</th>
                                <th>Created on</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $availableReport)
                                <tr>
                                    <td>{{ $availableReport['name'] }}</td>
                                    <td>
                                        @if ($availableReport['scheduled'])
                                            <span class="badge badge-info">Active: {{ $availableReport['scheduled']['active'] }}</span>
                                            <span class="badge badge-info">Inactive: {{ $availableReport['scheduled']['inactive'] }}</span>
                                        @else
                                            <span class="badge badge-muted">None</span>
                                        @endif
                                    </td>
                                    <td>{{ $availableReport['created_on'] }}</td>
                                    <td>
                                        <a class="btn btn-primary btn-small" href="{{ route('reports.schedule', ['report' => $availableReport['slug'], 'store_id' => $store->id, 'tab' => 'create']) }}">New Schedule</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($tab === 'actual')
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Schedule name</th>
                                <th>Report name</th>
                                <th>Schedule rule</th>
                                <th>Repeat forever?</th>
                                <th>Start date</th>
                                <th>End date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actualSchedules as $schedule)
                                <tr>
                                    <td>{{ $schedule['name'] }}</td>
                                    <td>{{ $schedule['report'] }}</td>
                                    <td>{{ $schedule['rule'] }}</td>
                                    <td>{{ $schedule['forever'] }}</td>
                                    <td>{{ $schedule['start'] }}</td>
                                    <td>{{ $schedule['end'] }}</td>
                                    <td>
                                        @if ($schedule['active'])
                                            <a class="btn btn-primary btn-small" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'create']) }}">Edit</a>
                                            <button class="btn btn-danger btn-small" type="button">Disable</button>
                                        @else
                                            <strong>Disabled</strong>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Schedule Name</th>
                                <th>Schedule Type</th>
                                <th>Run Date</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Report Status</th>
                                <th>Download</th>
                                <th>Stop Job</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reportHistory as $history)
                                <tr>
                                    <td>{{ $history['name'] }}</td>
                                    <td>{{ $history['type'] }}</td>
                                    <td>{{ $history['run'] }}</td>
                                    <td>{{ $history['start'] }}</td>
                                    <td>{{ $history['end'] }}</td>
                                    <td>success</td>
                                    <td><button class="btn btn-success btn-small" type="button">Download</button></td>
                                    <td style="text-align: center;">-</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="pagination">
                        <span class="active">1</span>
                        <span>2</span>
                        <span>3</span>
                        <span>4</span>
                        <span>5</span>
                        <span>...</span>
                        <span>&gt;</span>
                        <span>&raquo;</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
