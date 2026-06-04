<?php

namespace App\Services\Transformers;

/**
 * SUBPIC 转换器
 * 从 URL 中提取最后一个 "/" 之后的部分（文件名）
 */
class SubpicTransformer
{
    /**
     * 从 URL 中提取文件名
     *
     * @param string $url 图片 URL
     * @return string 文件名或空字符串
     */
    public function transform($url): string
    {
        try {
            if (empty($url)) {
                return "";
            }

            $parts = explode("/", $url);
            $filename = end($parts);

            return $filename ?: "";
        } catch (\Exception $e) {
            return "ERROR: " . $e->getMessage();
        }
    }
}
