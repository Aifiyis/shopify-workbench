<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报表计划 - 千兴工作台</title>
    @include('reports._styles')
</head>
<body>
    <div class="navbar">
        <h1>报表计划 - {{ $store->shop_name }}</h1>
        <div class="navbar-actions">
            <a href="{{ route('reports.index', ['store_id' => $store->id]) }}">返回报表</a>
        </div>
    </div>

    <div class="container">
        <nav class="tabs">
            <a class="tab {{ $tab === 'create' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'create']) }}">新增计划</a>
            <a class="tab {{ $tab === 'scheduled' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'scheduled']) }}">计划报表</a>
            <a class="tab {{ $tab === 'actual' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'actual']) }}">运行计划</a>
            <a class="tab {{ $tab === 'history' ? 'active' : '' }}" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'history']) }}">报表历史</a>
        </nav>

        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif

        <div class="panel">
            @if ($tab === 'create')
                <div class="schedule-header">
                    <h2 style="font-size: 16px;">计划信息</h2>
                </div>

                <form method="POST" action="{{ route('reports.schedule.save', ['report' => $report['slug'], 'store_id' => $store->id]) }}" class="schedule-form">
                    @csrf

                    <div class="form-row">
                        <label for="report_to_schedule">选择报表</label>
                        <select id="report_to_schedule" name="report">
                            @foreach ($reports as $availableReport)
                                <option value="{{ $availableReport['slug'] }}" {{ $availableReport['slug'] === $report['slug'] ? 'selected' : '' }}>
                                    {{ $availableReport['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="schedule_name">计划名称</label>
                        <input id="schedule_name" type="text" name="schedule_name" value="{{ $report['name'] }}">
                    </div>

                    <div class="form-row">
                        <label for="run_rule">运行频率</label>
                        <select id="run_rule" name="run_rule">
                            <option value="Run Immediately">立即运行</option>
                            <option value="Daily">每日</option>
                            <option value="Weekly">每周</option>
                            <option value="Monthly">每月</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="start_date">开始日期</label>
                        <input id="start_date" type="text" name="start_date" value="2026-05-23 00:00:00">
                    </div>

                    <div class="form-row">
                        <label for="end_date">结束日期</label>
                        <div class="inline-row">
                            <input id="end_date" type="text" name="end_date" value="2026-05-30 23:59:59">
                            <span style="font-weight: 700; color: #63717f;">或</span>
                            <label style="display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 400;">
                                <input type="checkbox" name="repeat_forever" value="1"> 永久重复
                            </label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">保存</button>
                        <a class="btn btn-light" href="{{ route('reports.index', ['store_id' => $store->id]) }}">取消</a>
                    </div>
                </form>
            @elseif ($tab === 'scheduled')
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>报表名称</th>
                                <th>计划任务</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $availableReport)
                                <tr>
                                    <td>{{ $availableReport['name'] }}</td>
                                    <td>
                                        @if ($availableReport['scheduled'])
                                            <span class="badge badge-info">启用：{{ $availableReport['scheduled']['active'] }}</span>
                                            <span class="badge badge-info">停用：{{ $availableReport['scheduled']['inactive'] }}</span>
                                        @else
                                            <span class="badge badge-muted">无</span>
                                        @endif
                                    </td>
                                    <td>{{ $availableReport['created_on'] }}</td>
                                    <td>
                                        <a class="btn btn-primary btn-small" href="{{ route('reports.schedule', ['report' => $availableReport['slug'], 'store_id' => $store->id, 'tab' => 'create']) }}">新增计划</a>
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
                                <th>计划名称</th>
                                <th>报表名称</th>
                                <th>计划规则</th>
                                <th>永久重复</th>
                                <th>开始日期</th>
                                <th>结束日期</th>
                                <th>操作</th>
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
                                            <a class="btn btn-primary btn-small" href="{{ route('reports.schedule', ['report' => $report['slug'], 'store_id' => $store->id, 'tab' => 'create']) }}">编辑</a>
                                            <button class="btn btn-danger btn-small" type="button">停用</button>
                                        @else
                                            <strong>已停用</strong>
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
                                <th>计划名称</th>
                                <th>计划类型</th>
                                <th>运行时间</th>
                                <th>开始日期</th>
                                <th>结束日期</th>
                                <th>报表状态</th>
                                <th>下载</th>
                                <th>停止任务</th>
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
                                    <td>成功</td>
                                    <td><button class="btn btn-success btn-small" type="button">下载</button></td>
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
