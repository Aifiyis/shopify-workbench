<?php

namespace App\Services;

use App\Models\ShopifyStore;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;

/**
 * Shopify API 交互服务
 * 使用 shopify-api-php SDK 或 Guzzle 直接调用 Shopify REST API
 */
class ShopifyService
{
    private $client;

    public function __construct()
    {
        $options = [];
        $caBundle = env('SHOPIFY_CA_BUNDLE');

        if ($caBundle) {
            $options['verify'] = $caBundle;
        } elseif (env('SHOPIFY_SSL_VERIFY') !== null) {
            $options['verify'] = filter_var(env('SHOPIFY_SSL_VERIFY'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->client = new Client($options);
    }

    /**
     * 获取订单列表
     * 支持日期范围筛选
     *
     * @param ShopifyStore $store Shopify 店铺
     * @param string|null $startDate 开始日期 (Y-m-d H:i:s)
     * @param string|null $endDate 结束日期 (Y-m-d H:i:s)
     * @return array 订单数组
     */
    public function fetchOrders(ShopifyStore $store, $startDate = null, $endDate = null): array
    {
        if (!$store->access_token) {
            throw new \Exception("Store {$store->shop_name} has no access token");
        }

        $query = [
            'status' => 'any',
            'limit' => 250,
        ];

        // 添加日期范围筛选
        if ($startDate && $endDate) {
            $query['created_at_min'] = date('c', strtotime($startDate));
            $query['created_at_max'] = date('c', strtotime($endDate));
        }

        try {
            $response = $this->callApi(
                $store,
                "GET",
                "/orders.json",
                $query
            );

            return $response['orders'] ?? [];
        } catch (\Exception $e) {
            \Log::error("Failed to fetch orders from {$store->shop_name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取订单详情（包含完整的行项目和属性）
     *
     * @param ShopifyStore $store Shopify 店铺
     * @param string $orderId 订单 ID
     * @return array 订单详情
     */
    public function getOrderDetails(ShopifyStore $store, $orderId): array
    {
        try {
            $response = $this->callApi(
                $store,
                "GET",
                "/orders/{$orderId}.json"
            );

            return $response['order'] ?? [];
        } catch (\Exception $e) {
            \Log::error("Failed to fetch order details: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 调用 Shopify REST API
     *
     * @param ShopifyStore $store 店铺
     * @param string $method HTTP 方法
     * @param string $endpoint API 端点
     * @param array $data 请求数据
     * @return array API 响应
     */
    private function callApi(ShopifyStore $store, $method = 'GET', $endpoint = '', $data = []): array
    {
        $url = "https://{$store->shop_url}/admin/api/2024-01{$endpoint}";

        $options = [
            'headers' => [
                'X-Shopify-Access-Token' => $store->access_token,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($method === 'GET' && !empty($data)) {
            $options['query'] = $data;
        } else if ($method !== 'GET' && !empty($data)) {
            $options['json'] = $data;
        }

        try {
            $response = $this->client->request($method, $url, $options);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\Exception $e) {
            \Log::error("Shopify API Error: " . $e->getMessage());
            throw $e;
        }
    }
}
