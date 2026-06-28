@extends('layouts.app')

@section('title', '工作台 - 千兴工作台')

@section('content')
<style>
    .dashboard-container {
        max-width: 1200px;
        margin: 10px auto 30px;
    }

    .dashboard-header {
        margin-bottom: 30px;
    }

    .dashboard-header h2 {
        font-size: 24px;
        font-weight: 700;
        color: #333;
    }

    .dashboard-header p {
        color: #666;
        margin-top: 5px;
    }

    .stores-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .store-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        color: inherit;
    }

    .store-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
    }

    .store-name {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .store-url {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
        word-break: break-all;
    }

    .store-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .empty-state {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 8px;
    }

    .empty-state p {
        color: #666;
        font-size: 16px;
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>选择店铺</h2>
        <p>选择要处理数据的店铺</p>
    </div>

    @if ($stores->isEmpty())
        <div class="empty-state">
            <p>暂无可用店铺，请联系管理员。</p>
        </div>
    @else
        <div class="stores-grid">
            @foreach ($stores as $store)
                <a href="{{ route('data-processing.index', ['store_id' => $store->id]) }}" class="store-card">
                    <div class="store-name">{{ $store->shop_name }}</div>
                    <div class="store-url">{{ $store->shop_url }}</div>
                    <div class="store-status {{ $store->is_active ? 'status-active' : 'status-inactive' }}">
                        {{ $store->is_active ? '启用' : '停用' }}
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
