<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>千兴工作台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.css">
    @if (file_exists(public_path('mix-manifest.json')) && file_exists(public_path('css/app.css')))
        <link rel="stylesheet" href="{{ mix('/css/app.css') }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js" defer></script>
    @if (file_exists(public_path('mix-manifest.json')) && file_exists(public_path('js/app.js')))
        <script src="{{ mix('/js/app.js') }}" defer></script>
    @endif
</head>
<body>
    @auth('admin')
        @php
            $admin = Auth::guard('admin')->user();
            $navItems = [
                ['label' => '工作台', 'route' => 'dashboard.index', 'patterns' => ['dashboard.*']],
                ['label' => '数据处理', 'route' => 'data-processing.index', 'patterns' => ['data-processing.*']],
                ['label' => 'SKU 产品类型', 'route' => 'sku-product-types.index', 'patterns' => ['sku-product-types.*', 'product-types.*']],
                ['label' => '订单处理配置', 'route' => 'order-processing.index', 'patterns' => ['order-processing.*']],
                ['label' => '工艺层级管理', 'route' => 'processing-crafts.index', 'patterns' => ['processing-crafts.*']],
            ];
        @endphp

        <div class="admin-shell">
            <header class="admin-header">
                <a class="admin-brand" href="{{ route('dashboard.index') }}">千兴工作台</a>
                <div class="admin-account">
                    <span class="admin-account-name">{{ $admin->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="admin-logout">退出登录</button>
                    </form>
                </div>
            </header>

            <aside class="admin-sidebar" aria-label="主导航">
                <nav class="admin-nav">
                    @foreach ($navItems as $item)
                        @php
                            $routeAvailable = Route::has($item['route']);
                            $isActive = request()->routeIs(...$item['patterns']);
                        @endphp
                        @if ($routeAvailable)
                            <a class="admin-nav-link {{ $isActive ? 'is-active' : '' }}"
                               href="{{ route($item['route']) }}"
                               @if ($isActive) aria-current="page" @endif>
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span class="admin-nav-link is-disabled" aria-disabled="true">{{ $item['label'] }}</span>
                        @endif
                    @endforeach

                    @if (Gate::forUser($admin)->allows('viewAny', \App\Models\Employee::class) || Gate::forUser($admin)->allows('viewAny', \App\Models\Position::class))
                        @php
                            $staffRoute = Route::has('employees.index') ? 'employees.index' : (Route::has('positions.index') ? 'positions.index' : null);
                            $staffActive = request()->routeIs('employees.*', 'positions.*');
                        @endphp
                        @if ($staffRoute)
                            <a class="admin-nav-link {{ $staffActive ? 'is-active' : '' }}"
                               href="{{ route($staffRoute) }}"
                               @if ($staffActive) aria-current="page" @endif>
                                员工与职位
                            </a>
                        @else
                            <span class="admin-nav-link is-disabled" aria-disabled="true">员工与职位</span>
                        @endif
                    @endif

                    @if (Gate::forUser($admin)->allows('viewAny', \App\Models\Admin::class))
                        @php($adminActive = request()->routeIs('admins.*'))
                        @if (Route::has('admins.index'))
                            <a class="admin-nav-link {{ $adminActive ? 'is-active' : '' }}"
                               href="{{ route('admins.index') }}"
                               @if ($adminActive) aria-current="page" @endif>
                                管理员管理
                            </a>
                        @else
                            <span class="admin-nav-link is-disabled" aria-disabled="true">管理员管理</span>
                        @endif
                    @endif
                </nav>
            </aside>

            <main class="admin-content">
    @else
        <main class="guest-content">
    @endauth
        <x-flash />
        <x-form-errors />
        @yield('content')
    </main>
    @auth('admin')
        </div>
    @endauth
    <x-confirm-delete />
</body>
</html>
