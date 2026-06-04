<?php

namespace App\Services\Transformers;

/**
 * URL 转换器
 * 从 line_item 的 properties 中提取 name="picture" 的值
 */
class UrlTransformer
{
    /**
     * 从 line_item 属性中提取图片 URL
     *
     * @param string|int $lineItemId 订单行项目 ID
     * @param object $order Shopify 订单对象
     * @return string 图片 URL 或空字符串
     */
    public function transform($lineItemId, $order): string
    {
        try {
            if (!isset($order->line_items) || !is_array($order->line_items)) {
                return "";
            }

            foreach ($order->line_items as $lineItem) {
                if (!isset($lineItem['id'])) {
                    continue;
                }

                if ((string)$lineItem['id'] === (string)$lineItemId) {
                    if (isset($lineItem['properties']) && is_array($lineItem['properties'])) {
                        foreach ($lineItem['properties'] as $prop) {
                            if (!empty($prop) && isset($prop['name'], $prop['value'])) {
                                if (stripos($prop['name'], 'picture') !== false) {
                                    return $prop['value'];
                                }
                            }
                        }
                    }
                }
            }

            return "";
        } catch (\Exception $e) {
            return "ERROR: " . $e->getMessage();
        }
    }
}
