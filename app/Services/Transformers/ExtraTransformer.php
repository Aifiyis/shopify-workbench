<?php

namespace App\Services\Transformers;

/**
 * EXTRA 转换器 - 最复杂的规则
 * 根据颜色、名称、数量、尺寸、标签、类型、pjsize 生成格式化的文件名
 *
 * Ruby 原始规则包含 40+ 个条件判断，生成不同产品类型的规范文件名
 */
class ExtraTransformer
{
    /**
     * 生成格式化的文件名
     *
     * @param string $color 颜色字段
     * @param string $name 产品名称
     * @param string $qty 数量
     * @param string $size 尺寸选项
     * @param string $tag 产品标签
     * @param string $type 产品类型
     * @param string $pjsize 睡衣尺寸（特定字段）
     * @return string 格式化的文件名
     */
    public function transform($color, $name, $qty, $size, $tag, $type, $pjsize): string
    {
        try {
            // 步骤1: 标准化标签
            $t = $this->normalizeTag($tag);

            // 步骤2: 标准化颜色代码
            $c = $this->normalizeColor($color);

            // 步骤3: 标准化尺寸
            $s = $this->normalizeSize($size);

            // 步骤4: 标准化睡衣尺寸
            $pjsize = $this->normalizePjSize($pjsize);

            // 步骤5: 根据产品类型生成文件名
            $d = $this->generateFilename($name, $type, $pjsize, $s, $qty, $t, $c);

            // 步骤6: 清理特殊文本
            $e = str_replace("As Picture", "", $d);
            $f = str_replace("3for2, ", "", $e);
            return str_replace(", 3for2", "", $f);

        } catch (\Exception $e) {
            return "ERROR: " . $e->getMessage();
        }
    }

    /**
     * 标准化标签
     */
    private function normalizeTag($tag): string
    {
        if (empty($tag)) {
            return "";
        }

        if (strpos($tag, "YXTB.") !== false) {
            return str_replace(".", "", $tag);
        } elseif ($tag === "PC") {
            return "PC.";
        } elseif (strpos($tag, "XSD") !== false || strpos($tag, "CP") !== false) {
            return $tag . ".";
        }

        return $tag;
    }

    /**
     * 标准化颜色为代码
     */
    private function normalizeColor($color): string
    {
        if (empty($color)) {
            return "";
        }

        $lowerColor = strtolower($color);

        // 颜色映射表
        $colorMap = [
            'weiss' => '1',
            'lightblue' => '2a',
            'light blue' => '2a',
            'royal blue' => '2b',
            'navy blau' => '2c',
            'navy' => '2c',
            'blau' => '2',
            'wine red' => '3a',
            'rot' => '3',
            'grün' => '4',
            'gelb' => '5',
            'orange' => '6',
            'brown' => '6a',
            'grau' => '7',
            'schwarz' => '8',
            'rosa' => '9',
            'light pink' => '9a',
            'darkpink' => '9b',
            'dark pink' => '9b',
            'lila' => '0',
        ];

        foreach ($colorMap as $key => $code) {
            if (strpos($lowerColor, $key) !== false) {
                return $code;
            }
        }

        return $color;
    }

    /**
     * 标准化尺寸
     */
    private function normalizeSize($size): string
    {
        if (empty($size)) {
            return "";
        }

        $sizeMap = [
            'S-Frau' => 'S',
            '(S)' => 'S',
            'M-Taille' => 'M',
            '(M)' => 'M',
            'XXL-Taille' => 'XXL',
            '(XXL)' => 'XXL',
            'XL-Taille' => 'XL',
            '(XL)' => 'XL',
            'L-Mann' => 'L',
            'L-Taille' => 'L',
            '(L)' => 'L',
            '12in' => '30cm',
            '15in' => '40cm',
            '18in' => '45cm',
            '20in' => '50cm',
            '24in' => '60cm',
            '28in' => '70cm',
            'Pants+Shirt' => 'Set',
            'Shirt Only' => 'Top',
            'Pants Only' => 'Bottom',
            'Hose' => 'Bottom',
            'Oben' => 'Top',
        ];

        // 精确匹配
        if (isset($sizeMap[$size])) {
            return $sizeMap[$size];
        }

        // 模糊匹配
        foreach ($sizeMap as $key => $value) {
            if (strpos($size, $key) !== false) {
                return str_replace($key, $value, $size);
            }
        }

        // 如果包含 "-"，取第一部分
        if (strpos($size, "-") !== false) {
            $parts = explode("-", $size);
            return $parts[0];
        }

        return $size;
    }

    /**
     * 标准化睡衣尺寸
     */
    private function normalizePjSize($pjsize): string
    {
        if (empty($pjsize)) {
            return "";
        }

        $pjsize = str_replace("Hose", "Bottom", $pjsize);
        $pjsize = str_replace("Oben", "Top", $pjsize);

        return $pjsize;
    }

    /**
     * 根据产品类型生成文件名
     * 每种类型有不同的命名规则
     */
    private function generateFilename($name, $type, $pjsize, $s, $qty, $t, $c): string
    {
        if (empty($type)) {
            return "{$name}({$qty}){$s}-{$t}{$c}.jpg";
        }

        // 所有 if-elseif 条件按照 Ruby 原代码顺序转译
        if (strpos($type, "Custom Pajamas") !== false) {
            return "{$name}-{$pjsize}-{$s}-{$qty}G-{$t}-{$c}.jpg";
        } elseif (strpos($type, "long pajamas pants for men") !== false || strpos($type, "Pajamas Pants For Men") !== false) {
            $position = !empty($pjsize) ? $pjsize : "Bottom";
            return "{$name}-SYlongnan-{$position}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "long pajamas pants for women") !== false) {
            $position = !empty($pjsize) ? $pjsize : "Bottom";
            return "{$name}-SYlongnv-{$position}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Short V-neck Pajamas Set") !== false) {
            return "{$name}-SYshortV-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "long pajamas for men") !== false) {
            return "{$name}-SYlongnan-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "long pajamas for women") !== false) {
            return "{$name}-SYlongnv-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "short pajamas for men") !== false) {
            return "{$name}-SYshortnan-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "short pajamas for women") !== false) {
            return "{$name}-SYshortnv-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "satin pajamas short for women") !== false) {
            return "{$name}-satinSYshortnv-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "satin pajamas short for men") !== false) {
            return "{$name}-satinSYshortnan-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "satin pajamas") !== false) {
            return "{$name}-satinSY-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "short pajamas for kids") !== false) {
            $realSize = strpos($s, '(') ? explode('(', $s)[0] : $s;
            return "{$name}-SYshortKids-{$pjsize}-{$realSize}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "long pajamas for kid") !== false) {
            $realSize = strpos($s, '(') ? explode('(', $s)[0] : $s;
            return "{$name}-SYlongKids-{$pjsize}-{$realSize}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Kid Pajamas") !== false) {
            return "{$name}-SYkids-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Baby Bodysuit") !== false) {
            return "{$name}-PPF-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "long baby bodysuit") !== false) {
            return "{$name}-PPFlong-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Face Bodysuit") !== false) {
            return "{$name}-2PPF-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Night Dress") !== false || strpos($type, "Nightdress") !== false) {
            return "{$name}-SQ001-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Custom Shirt") !== false) {
            return "{$name}-TS-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Dog Pajamas") !== false) {
            return "{$name}-DP-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Photo Hoodies") !== false) {
            return "{$name}-PH-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Photo Robe") !== false) {
            return "{$name}-PR-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Sweatshirt") !== false) {
            return "{$name}-Sweatshirt-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Blanket Hoodie For Adult") !== false) {
            return "{$name}-BlkHoodie-{$pjsize}-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Hawaiian Shirt") !== false) {
            return "{$name}-HA-{$s}-{$qty}G-{$t}{$c}.jpg";
        } elseif (strpos($type, "Long pajamas set") !== false) {
            // 复杂的尺寸解析
            preg_match('/\(([^)]+)\)/', $s, $matches);
            $realSize = isset($matches[1]) ? $matches[1] : $s;
            $gender = explode('(', $s)[0];

            if (strpos($gender, "WoMänner") !== false) {
                $sex = 'nv';
            } elseif (strpos($gender, "Männer") !== false) {
                $sex = 'nan';
            } elseif (strpos($gender, "Kinder") !== false) {
                $sex = 'Kids';
            } else {
                $sex = '(!error)';
            }

            return "{$name}-SYlong{$sex}-Set{$realSize}-{$qty}G-{$t}{$c}.jpg";
        } else {
            // 默认格式
            return "{$name}({$qty}){$s}-{$t}{$c}.jpg";
        }
    }
}
