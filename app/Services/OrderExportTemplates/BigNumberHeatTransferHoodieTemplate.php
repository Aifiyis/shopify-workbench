<?php

namespace App\Services\OrderExportTemplates;

class BigNumberHeatTransferHoodieTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'big_number_heat_transfer_hoodie';
    }

    public function label()
    {
        return '大数字烫画卫衣';
    }

    public function supportedChineseNames()
    {
        return ['大数字烫画卫衣'];
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
            '左袖信息',
            '左袖图标',
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '备注',
            '袖子位置',
            '袖子线色',
            '设计稿',
            '胸口信息',
            '胸口信息颜色',
            '胸口位置',
            '贺卡/包装',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 0);

        $this->setHeaderValueIfBlank($values, '胸口位置', '胸部中央');

        if ($this->isQk3385($row)) {
            $flagText = $this->firstExactAttributeValue($attributes, 'Text On The Flag');

            if ($flagText !== '') {
                $this->setHeaderValue($values, '右袖信息', $flagText);
                $this->setHeaderValue($values, '备注', '文本在flag里');
            }
        }

        if (($values[$this->headerIndex('左袖图标')] ?? '') !== ''
            || ($values[$this->headerIndex('左袖信息')] ?? '') !== '') {
            $this->setHeaderValue($values, '袖子位置', '左袖');
        }

        if (($values[$this->headerIndex('右袖图标')] ?? '') !== '') {
            $values[15] = '右袖';
        }

        return $values;
    }

    private function isQk3385(array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === 'CS-QK3385-TH' || strpos($sku, 'CS-QK3385-TH') !== false;
    }

    private function firstExactAttributeValue(array $attributes, $targetName)
    {
        foreach ($attributes as $attribute) {
            if (strcasecmp(trim((string) ($attribute['name'] ?? '')), $targetName) === 0) {
                return trim((string) ($attribute['value'] ?? ''));
            }
        }

        return '';
    }
}
