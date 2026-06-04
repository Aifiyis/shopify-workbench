<?php

namespace App\Services\Transformers;

/**
 * NAME 转换器
 * 从订单的所有 line_items 中提取 title，用逗号连接
 */
class NameTransformer
{
    /**
     * 提取所有产品名称并连接
     *
     * @param object $order Shopify 订单对象
     * @return string 用逗号分隔的产品名称
     */
    public function transform($order): string
    {
        try {
            if (!isset($order->line_items) || !is_array($order->line_items)) {
                return "";
            }

            $titles = [];
            foreach ($order->line_items as $lineItem) {
                if (isset($lineItem['title'])) {
                    $titles[] = $lineItem['title'];
                }
            }

            return implode(",", $titles);
        } catch (\Exception $e) {
            return "ERROR: " . $e->getMessage();
        }
    }
}
