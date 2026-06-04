<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shopify Workbench')</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            font-weight: bold;
            color: #333;
        }

        .navbar a {
            text-decoration: none;
            color: #333;
            margin: 0 15px;
        }

        .navbar a:hover {
            color: #0066cc;
        }

        .sidebar {
            background: white;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar a {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .sidebar a:hover {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1><a href="{{ route('dashboard.index') }}" style="color: inherit; text-decoration: none;">Shopify Workbench</a></h1>
        <div>
            @auth('admin')
                <span style="margin-right: 15px;">{{ Auth::guard('admin')->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" style="background: none; border: none; color: #0066cc; cursor: pointer; text-decoration: underline;">Logout</button>
                </form>
            @endauth
        </div>
    </div>

    <div style="display: flex;">
        <div style="width: 200px;">
            <div class="sidebar">
                <a href="{{ route('dashboard.index') }}">Dashboard</a>
                @auth('admin')
                    @if(Auth::guard('admin')->user()->role === 'super')
                        <a href="{{ route('admins.index') }}">Admin Management</a>
                    @elseif(Auth::guard('admin')->user()->role === 'manager')
                        <a href="{{ route('admins.index') }}">Manage Team</a>
                    @endif
                @endauth
                <a href="{{ route('data-processing.index') }}">Data Processing</a>
            </div>
        </div>

        <div style="flex: 1; padding: 20px;">
            @yield('content')
        </div>
    </div>
</body>
</html>
