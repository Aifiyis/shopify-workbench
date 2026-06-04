<?php

namespace App\Services\Transformers;

/**
 * GETNOTES 转换器
 * 从 line_item 的 properties 中提取 name="notes" 的值
 */
class GetnotesTransformer
{
    /**
     * 从 line_item 属性中提取用户笔记
     *
     * @param string|int $lineItemId 订单行项目 ID
     * @param object $order Shopify 订单对象
     * @return string 笔记内容或空字符串
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
                                if (stripos($prop['name'], 'notes') !== false) {
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
