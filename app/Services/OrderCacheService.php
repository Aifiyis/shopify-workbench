<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\ShopifyStore;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 订单缓存服务
 * 管理本地数据库中的订单缓存
 */
class OrderCacheService
{
    private $orderFieldTransformer;
    private $cacheTtlHours = 1; // 默认缓存 1 小时

    public function __construct(OrderFieldTransformer $transformer)
    {
        $this->orderFieldTransformer = $transformer;
    }

    /**
     * 缓存订单到数据库
     *
     * @param array $orders Shopify 订单数组
     * @param ShopifyStore $store 店铺
     * @return void
     */
    public function cacheOrders(array $orders, ShopifyStore $store): void
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addHours($this->cacheTtlHours);

        foreach ($orders as $shopifyOrder) {
            $this->cacheOrder($shopifyOrder, $store, $now, $expiresAt);
        }

        // 更新店铺最后同步时间
        $store->update(['last_synced_at' => $now]);
    }

    /**
     * 缓存单个订单
     *
     * @param array $shopifyOrder Shopify 订单数据
     * @param ShopifyStore $store 店铺
     * @param Carbon $now 当前时间
     * @param Carbon $expiresAt 过期时间
     * @return Order
     */
    private function cacheOrder(array $shopifyOrder, ShopifyStore $store, Carbon $now, Carbon $expiresAt): Order
    {
        // 创建或更新订单
        $order = Order::updateOrCreate(
            ['shopify_order_id' => $shopifyOrder['id']],
            [
                'store_id' => $store->id,
                'order_date' => $shopifyOrder['created_at'] ?? $now,
                'order_name' => $shopifyOrder['name'] ?? '',
                'customer_name' => $shopifyOrder['customer']['default_address']['name'] ?? $shopifyOrder['customer']['first_name'] . ' ' . $shopifyOrder['customer']['last_name'] ?? '',
                'total_price' => $shopifyOrder['total_price'] ?? 0,
                'currency' => $shopifyOrder['currency'] ?? 'USD',
                'status' => $shopifyOrder['fulfillment_status'] ?? 'pending',
                'line_items_count' => count($shopifyOrder['line_items'] ?? []),
                'cached_at' => $now,
                'expires_at' => $expiresAt,
            ]
        );

        // 转换并缓存行项目
        $lineItems = $this->orderFieldTransformer->transformOrder($shopifyOrder);
        foreach ($lineItems as $lineItem) {
            OrderLineItem::updateOrCreate(
                ['shopify_line_item_id' => $lineItem['shopify_line_item_id']],
                array_merge($lineItem, ['order_id' => $order->id])
            );
        }

        return $order;
    }

    /**
     * 获取缓存的订单
     *
     * @param ShopifyStore $store 店铺
     * @param array $filters 筛选条件 ['start_date' => '...', 'end_date' => '...']
     * @return Collection 订单集合
     */
    public function getCachedOrders(ShopifyStore $store, array $filters = []): Collection
    {
        $query = Order::where('store_id', $store->id);

        // 日期范围筛选
        if (isset($filters['start_date'])) {
            $query->whereDate('order_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('order_date', '<=', $filters['end_date']);
        }

        return $query->with('lineItems')->orderBy('order_date', 'desc')->get();
    }

    /**
     * 检查缓存是否有效
     *
     * @param ShopifyStore $store 店铺
     * @return bool
     */
    public function isCacheValid(ShopifyStore $store): bool
    {
        if (!$store->last_synced_at) {
            return false;
        }

        $expiresAt = $store->last_synced_at->addHours($this->cacheTtlHours);
        return $expiresAt->isFuture();
    }

    /**
     * 清除过期的缓存
     *
     * @return int 删除的订单数
     */
    public function clearExpired(): int
    {
        return Order::where('expires_at', '<', Carbon::now())->delete();
    }

    /**
     * 设置缓存 TTL (小时)
     *
     * @param int $hours
     * @return $this
     */
    public function setCacheTtl(int $hours)
    {
        $this->cacheTtlHours = $hours;
        return $this;
    }
}
