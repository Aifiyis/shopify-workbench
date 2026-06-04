<?php

namespace App\Services;

use App\Services\Transformers\{
    NameTransformer,
    ValTransformer,
    UrlTransformer,
    SubpicTransformer,
    ExtraTransformer,
    GetnotesTransformer
};

/**
 * 订单字段转换服务 - 主入口
 * 应用所有 Ruby 规则转译到订单数据
 */
class OrderFieldTransformer
{
    private $nameTransformer;
    private $valTransformer;
    private $urlTransformer;
    private $subpicTransformer;
    private $extraTransformer;
    private $getnotesTransformer;

    public function __construct()
    {
        $this->nameTransformer = new NameTransformer();
        $this->valTransformer = new ValTransformer();
        $this->urlTransformer = new UrlTransformer();
        $this->subpicTransformer = new SubpicTransformer();
        $this->extraTransformer = new ExtraTransformer();
        $this->getnotesTransformer = new GetnotesTransformer();
    }

    /**
     * 转换订单的所有行项目
     * 应用所有转换规则
     *
     * @param object $order Shopify 订单对象
     * @return array 转换后的行项目数组
     */
    public function transformOrder($order): array
    {
        $results = [];

        if (!isset($order['line_items']) || !is_array($order['line_items'])) {
            return $results;
        }

        foreach ($order['line_items'] as $lineItem) {
            $results[] = $this->transformLineItem($lineItem, $order);
        }

        return $results;
    }

    /**
     * 转换单个行项目
     * 应用所有字段转换规则
     *
     * @param array $lineItem Shopify 行项目
     * @param array $order 完整的 Shopify 订单
     * @return array 转换后的行项目数据
     */
    private function transformLineItem(array $lineItem, array $order): array
    {
        // 提取基本字段
        $productTitle = $lineItem['title'] ?? '';
        $productType = $lineItem['product_type'] ?? '';
        $quantity = $lineItem['quantity'] ?? 1;
        $lineItemId = $lineItem['id'] ?? '';

        // 提取选项
        $option1 = '';
        $option3 = '';
        if (isset($lineItem['properties']) && is_array($lineItem['properties'])) {
            foreach ($lineItem['properties'] as $prop) {
                if (isset($prop['name'])) {
                    if ($prop['name'] === 'Option 1') {
                        $option1 = $prop['value'] ?? '';
                    }
                    if ($prop['name'] === 'Option 3') {
                        $option3 = $prop['value'] ?? '';
                    }
                }
            }
        }

        // 获取 SKU
        $sku = $lineItem['sku'] ?? '';

        // 获取产品标签
        $productTags = $lineItem['product_tags'] ?? '';
        if (is_array($productTags)) {
            $productTags = implode(",", $productTags);
        }

        // 应用转换规则
        $pictureUrl = $this->urlTransformer->transform($lineItemId, (object)$order);
        $picName = $this->subpicTransformer->transform($pictureUrl);

        // 对于 EXTRA 规则，需要从 properties 中提取颜色、尺寸等信息
        $color = $this->extractPropertyValue($lineItem, 'color');
        $size = $this->extractPropertyValue($lineItem, 'size');
        $pjsize = $this->extractPropertyValue($lineItem, 'pjsize');

        $extraDetails = $this->extraTransformer->transform(
            $color,
            $productTitle,
            $quantity,
            $size,
            $productTags,
            $productType,
            $pjsize
        );

        $customText = $this->getnotesTransformer->transform($lineItemId, (object)$order);

        return [
            'shopify_line_item_id' => $lineItemId,
            'product_title' => $productTitle,
            'product_type' => $productType,
            'quantity' => $quantity,
            'option1' => $option1,
            'option3' => $option3,
            'product_tags' => $productTags,
            'sku' => $sku,
            'multi_types' => $this->valTransformer->transform($productTitle),
            'picture_url' => $pictureUrl,
            'pic_name' => $picName,
            'extra_details' => $extraDetails,
            'custom_text' => $customText,
            'raw_properties' => $lineItem['properties'] ?? [],
        ];
    }

    /**
     * 从 properties 中提取指定的属性值
     *
     * @param array $lineItem 行项目
     * @param string $propertyName 属性名称
     * @return string 属性值或空字符串
     */
    private function extractPropertyValue(array $lineItem, string $propertyName): string
    {
        if (!isset($lineItem['properties']) || !is_array($lineItem['properties'])) {
            return '';
        }

        foreach ($lineItem['properties'] as $prop) {
            if (isset($prop['name']) && strtolower($prop['name']) === strtolower($propertyName)) {
                return $prop['value'] ?? '';
            }
        }

        return '';
    }
}
