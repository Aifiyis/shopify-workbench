<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Shopify Workbench</title>
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
            font-size: 24px;
            color: #333;
        }

        .navbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .navbar-user {
            color: #666;
            font-size: 14px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
        }

        .btn-logout:hover {
            background: #c0392b;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .store-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Shopify Workbench</h1>
        <div class="navbar-actions">
            <div class="navbar-user">
                Welcome, <strong>{{ $admin->name }}</strong>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div style="margin-bottom: 30px;">
            <h2>Select a Store</h2>
            <p style="color: #666; margin-top: 5px;">Choose a store to manage reports</p>
        </div>

        @if ($stores->isEmpty())
            <div style="text-align: center; padding: 50px; background: white; border-radius: 10px;">
                <p style="color: #666; font-size: 16px;">
                    ❌ No stores available. Please contact the administrator.
                </p>
            </div>
        @else
            <div class="stores-grid">
                @foreach ($stores as $store)
                    <a href="{{ route('data-processing.index', ['store_id' => $store->id]) }}" class="store-card">
                        <div class="store-name">{{ $store->shop_name }}</div>
                        <div class="store-url">{{ $store->shop_url }}</div>
                        <div class="store-status {{ $store->is_active ? 'status-active' : 'status-inactive' }}">
                            {{ $store->is_active ? '✓ Active' : '✗ Inactive' }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
