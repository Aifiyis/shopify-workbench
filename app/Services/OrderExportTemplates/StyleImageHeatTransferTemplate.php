<?php

namespace App\Services\OrderExportTemplates;

class StyleImageHeatTransferTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'style_image_heat_transfer';
    }

    public function label()
    {
        return '款式图烫画';
    }

    public function supportedChineseNames()
    {
        return ['款式图烫画'];
    }

    public function headers()
    {
        return $this->withProductSpecsHeader([
            '导表日期',
            '订单号',
            '款式图',
            '是否做货',
            '是否发货',
            '款式图',
            '衣服颜色',
            '尺码',
            '数量',
            '设计图',
            '胸口信息',
            '后背信息',
            '胸口文本颜色',
            '后背文本颜色',
            '烫画位置',
            '设计风格',
            '贺卡/包装',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 0);

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerName = strtolower($name);

            if ($name === '' || $value === '') {
                continue;
            }

            if ((strpos($lowerName, 'year') !== false || strpos($lowerName, 'est') !== false)
                && strpos($lowerName, 'color') === false) {
                $this->routeChestTextByPlacementBehavior($values, $row, $context, $value, true);
            }

            if (strpos($lowerName, 'recipient') !== false) {
                $this->routeRecipientByPlacementBehavior($values, $row, $context, $value);
            }

            if (strpos($lowerName, 'color') !== false
                && (strpos($lowerName, 'year') !== false || strpos($lowerName, 'est') !== false)
                && $this->routeYearColorByPlacementBehavior($values, $row, $context, $value)) {
                continue;
            }

            if (strpos($lowerName, 'color') !== false
                && (strpos($lowerName, 'back') !== false || strpos($lowerName, 'year') !== false)) {
                $this->appendFirstHeaderValue(
                    $values,
                    ['后背信息'],
                    $this->translateOptionColorValue(
                        $value,
                        $context['color_lookup'] ?? [],
                        $context['color_translation_resolver'] ?? null
                    )
                );
            }

            if (strpos($lowerName, 'design') !== false) {
                $imagePath = $this->resolveOptionImage($context, $row, $name, $value);

                $this->setHeaderValue($values, '设计风格', $imagePath !== '' ? $imagePath : $value);
            }
        }

        $this->applyFixedTextColorRules($values, $row);

        return $values;
    }

    private function routeYearColorByPlacementBehavior(array &$values, array $row, array $context, $value)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? $row['sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        if ($cleanedSku !== 'CS-QK0138-TH' && strpos($sku, 'CS-QK0138-TH') === false) {
            return false;
        }

        $rule = $this->resolveSkuPlacementRule($context, $row);

        if (($rule['placement_behavior'] ?? '') !== 'mirror_chest_to_back') {
            return false;
        }

        $translatedColor = $this->translateOptionColorValue(
            $value,
            $context['color_lookup'] ?? [],
            $context['color_translation_resolver'] ?? null
        );

        $this->appendIndexedValue($values, 12, $translatedColor);
        $this->appendIndexedValue($values, 13, $translatedColor);

        return true;
    }

    private function appendIndexedValue(array &$values, $index, $value)
    {
        if (($values[$index] ?? '') === '') {
            $values[$index] = $value;
        } else {
            $values[$index] .= "\n" . $value;
        }
    }

    private function routeRecipientByPlacementBehavior(array &$values, array $row, array $context, $value)
    {
        $this->routeChestTextByPlacementBehavior($values, $row, $context, $value, false);
    }

    private function routeChestTextByPlacementBehavior(array &$values, array $row, array $context, $value, $fallbackToChest)
    {
        $rule = $this->resolveSkuPlacementRule($context, $row);
        $behavior = $rule['placement_behavior'] ?? '';

        if ($behavior === 'mirror_chest_to_back') {
            $this->appendFirstHeaderValue($values, ['胸口信息'], $value);
            $this->appendFirstHeaderValue($values, ['后背信息'], $value);
            return;
        }

        if ($behavior === 'only_chest') {
            $this->appendFirstHeaderValue($values, ['胸口信息'], $value);
            return;
        }

        if ($fallbackToChest) {
            $this->appendFirstHeaderValue($values, ['胸口信息'], $value);
        }
    }

    private function resolveSkuPlacementRule(array $context, array $row)
    {
        $resolver = $context['sku_placement_resolver'] ?? null;

        if ($resolver === null || !method_exists($resolver, 'resolveRule')) {
            return [];
        }

        $rule = $resolver->resolveRule(
            $row['cleaned_sku'] ?? $row['sku'] ?? '',
            $row['website'] ?? ''
        );

        return is_array($rule) ? $rule : [];
    }

    private function applyFixedTextColorRules(array &$values, array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? $row['sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        if ($cleanedSku === 'CS-LXJ7445-TH' || strpos($sku, 'CS-LXJ7445-TH') !== false) {
            $this->setHeaderValue($values, '胸口文本颜色', '红色');
            $this->setHeaderValue($values, '后背文本颜色', '红色');
            return;
        }

        if ($cleanedSku === 'CS-QK6009-TH' || strpos($sku, 'CS-QK6009-TH') !== false) {
            $this->setHeaderValue($values, '胸口文本颜色', '黑色');
            $this->setHeaderValue($values, '后背文本颜色', '白色');
        }
    }
}
