<?php

use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\ShopifyStore;
use App\Services\OrderCacheService;
use App\Services\ShopifyService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'status';

$safeJson = function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
};

$stores = ShopifyStore::all();

$safeJson([
    'config' => [
        'database' => config('database.default'),
        'shopify_api_key_set' => env('SHOPIFY_API_KEY') ? true : false,
        'shopify_api_secret_set' => env('SHOPIFY_API_SECRET') ? true : false,
        'shopify_access_token_env_set' => env('SHOPIFY_ACCESS_TOKEN') ? true : false,
    ],
    'local_cache' => [
        'orders' => Order::count(),
        'line_items' => OrderLineItem::count(),
    ],
    'stores' => $stores->map(function (ShopifyStore $store) {
        return [
            'id' => $store->id,
            'shop_name' => $store->shop_name,
            'shop_url' => $store->shop_url,
            'is_active' => (bool) $store->is_active,
            'has_access_token' => (bool) $store->access_token,
            'last_synced_at' => $store->last_synced_at ? $store->last_synced_at->toDateTimeString() : null,
        ];
    })->values()->all(),
]);

if ($mode !== 'fetch') {
    exit(0);
}

$store = $stores->first(function (ShopifyStore $candidate) {
    return $candidate->is_active && $candidate->access_token;
});

if (!$store) {
    $safeJson([
        'fetch' => [
            'success' => false,
            'message' => 'No active store with access token was found.',
        ],
    ]);
    exit(2);
}

try {
    /** @var ShopifyService $shopifyService */
    $shopifyService = $app->make(ShopifyService::class);
    /** @var OrderCacheService $cacheService */
    $cacheService = $app->make(OrderCacheService::class);

    $orders = $shopifyService->fetchOrders($store);
    $cacheService->cacheOrders($orders, $store);

    $safeJson([
        'fetch' => [
            'success' => true,
            'store_id' => $store->id,
            'store' => $store->shop_name,
            'orders_fetched' => count($orders),
            'orders_cached_total' => Order::where('store_id', $store->id)->count(),
            'line_items_cached_total' => OrderLineItem::whereHas('order', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })->count(),
        ],
    ]);
} catch (Throwable $e) {
    $safeJson([
        'fetch' => [
            'success' => false,
            'error_class' => get_class($e),
            'message' => $e->getMessage(),
        ],
    ]);
    exit(1);
}
