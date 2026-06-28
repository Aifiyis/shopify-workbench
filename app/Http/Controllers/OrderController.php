<?php

namespace App\Http\Controllers;

use App\Models\ShopifyStore;
use App\Services\ShopifyService;
use App\Services\OrderCacheService;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    private $shopifyService;
    private $orderCacheService;
    private $excelExportService;

    public function __construct(
        ShopifyService $shopifyService,
        OrderCacheService $orderCacheService,
        ExcelExportService $excelExportService
    ) {
        $this->middleware('auth:admin');
        $this->shopifyService = $shopifyService;
        $this->orderCacheService = $orderCacheService;
        $this->excelExportService = $excelExportService;
    }

    /**
     * 显示订单列表
     */
    public function index(Request $request)
    {
        $storeId = $request->input('store_id');
        $admin = Auth::guard('admin')->user();

        // 验证权限
        $store = ShopifyStore::findOrFail($storeId);
        if (!$admin->canAccessStore($storeId)) {
            abort(403, '无权访问此店铺');
        }

        // 获取日期范围
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // 获取缓存订单
        $filters = [];
        if ($startDate) $filters['start_date'] = $startDate;
        if ($endDate) $filters['end_date'] = $endDate;

        $orders = $this->orderCacheService->getCachedOrders($store, $filters);

        return view('orders.index', [
            'store' => $store,
            'orders' => $orders,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * 刷新订单数据
     */
    public function refresh(Request $request)
    {
        $storeId = $request->input('store_id');
        $admin = Auth::guard('admin')->user();

        // 验证权限
        if (!$admin->canAccessStore($storeId)) {
            return response()->json(['success' => false, 'message' => '无权执行此操作'], 403);
        }

        $store = ShopifyStore::findOrFail($storeId);

        try {
            // 获取最新订单
            $orders = $this->shopifyService->fetchOrders($store);

            // 缓存订单
            $this->orderCacheService->cacheOrders($orders, $store);

            return response()->json([
                'success' => true,
                'message' => '订单刷新成功',
                'count' => count($orders),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '刷新订单失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 导出订单为 Excel
     */
    public function export(Request $request)
    {
        $storeId = $request->input('store_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $admin = Auth::guard('admin')->user();

        // 验证权限
        if (!$admin->canAccessStore($storeId)) {
            return response()->json(['success' => false, 'message' => '无权执行此操作'], 403);
        }

        try {
            $store = ShopifyStore::findOrFail($storeId);

            // 获取订单
            $filters = [];
            if ($startDate) $filters['start_date'] = $startDate;
            if ($endDate) $filters['end_date'] = $endDate;

            $orders = $this->orderCacheService->getCachedOrders($store, $filters);

            if ($orders->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '所选日期范围内没有订单',
                ], 404);
            }

            // 导出 Excel
            $filepath = $this->excelExportService->export(
                $orders,
                $startDate ?? 'all',
                $endDate ?? 'all'
            );

            return response()->json([
                'success' => true,
                'download_url' => $this->excelExportService->getDownloadUrl($filepath),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '导出订单失败：' . $e->getMessage(),
            ], 500);
        }
    }
}
