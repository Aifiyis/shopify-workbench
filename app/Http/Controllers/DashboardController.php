<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\ShopifyStore;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * 显示仪表板
     */
    public function index()
    {
        $admin = Auth::guard('admin')->user();

        // 获取管理员可访问的店铺
        if ($admin->role === 'super') {
            $stores = ShopifyStore::where('is_active', true)->get();
        } else {
            $stores = $admin->stores()->where('is_active', true)->get();
        }

        return view('dashboard.index', [
            'admin' => $admin,
            'stores' => $stores,
        ]);
    }
}
