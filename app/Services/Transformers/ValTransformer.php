<?php

namespace App\Services\Transformers;

/**
 * VAL 转换器
 * 检查 title 是否包含 "3,99"，包含返回 "H"，否则返回空
 */
class ValTransformer
{
    /**
     * 检查是否包含特殊价格标记
     *
     * @param string $title 产品标题
     * @return string "H" 或空字符串
     */
    public function transform($title): string
    {
        try {
            if (empty($title)) {
                return "";
            }

            if (strpos($title, "3,99") !== false) {
                return "H";
            }

            return "";
        } catch (\Exception $e) {
            return "ERROR: " . $e->getMessage();
        }
    }
}
