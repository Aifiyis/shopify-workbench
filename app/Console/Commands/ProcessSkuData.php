<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PHPExcel_IOFactory;

class ProcessSkuData extends Command
{
    protected $signature = 'sku:process';
    protected $description = 'Process SKU data from Excel and generate cleaned JSON';

    public function handle()
    {
        $this->info('开始处理SKU数据...');

        try {
            // Step 1: 读取 Excel 数据
            $filePath = storage_path('app/private/sku-to-product_type.xlsx');
            $this->info("读取文件: {$filePath}");

            $excel = PHPExcel_IOFactory::load($filePath);
            $sheet = $excel->getSheetByName('all');
            $highestRow = $sheet->getHighestRow();

            $allData = [];
            $skuList = [];

            // 读取所有数据
            for ($row = 2; $row <= $highestRow; $row++) {
                $sku = trim($sheet->getCell('A' . $row)->getValue());
                $productType = trim($sheet->getCell('B' . $row)->getValue());
                $chineseName = trim($sheet->getCell('C' . $row)->getValue());
                $topPeopleCount = trim($sheet->getCell('D' . $row)->getValue());

                if (empty($sku)) {
                    continue;
                }

                $allData[] = [
                    'sku' => $sku,
                    'product_type' => $productType,
                    'chinese_name' => $chineseName,
                    'top_people_count' => $topPeopleCount,
                ];

                $skuList[] = $sku;
            }

            $this->info("✓ 读取了 " . count($allData) . " 条记录");

            // Step 2: 读取 color 和 style 数据（从 JSON 或 sheet）
            $colorData = [];
            $styleData = [];

            // 首先尝试读取已缓存的 JSON
            $colorJsonPath = storage_path('app/private/lookups/color_lookup.json');
            $styleJsonPath = storage_path('app/private/lookups/style_lookup.json');

            if (file_exists($colorJsonPath)) {
                $colorData = json_decode(file_get_contents($colorJsonPath), true) ?? [];
                $this->info("✓ 从 JSON 读取 color 数据: " . count($colorData) . " 条记录");
            }

            if (file_exists($styleJsonPath)) {
                $styleData = json_decode(file_get_contents($styleJsonPath), true) ?? [];
                $this->info("✓ 从 JSON 读取 style 数据: " . count($styleData) . " 条记录");
            }

            // 如果 JSON 不存在，尝试从 Excel sheet 读取
            if (empty($colorData) || empty($styleData)) {
                $this->warn("⚠ 未找到 color/style JSON，尝试从 Excel sheet 读取...");

                try {
                    $sheetNames = [];
                    for ($i = 0; $i < $excel->getSheetCount(); $i++) {
                        $sheetNames[] = $excel->getSheet($i)->getTitle();
                    }
                    $this->info("可用的 Sheet: " . implode(', ', $sheetNames));

                    // 检查是否存在对应的 sheet
                    foreach ($excel->getAllSheets() as $sheet) {
                        $name = strtolower($sheet->getTitle());
                        if (strpos($name, 'color') !== false && empty($colorData)) {
                            $colorData = $this->readLookupSheet($sheet);
                            $this->info("✓ 从 {$sheet->getTitle()} sheet 读取 color: " . count($colorData) . " 条");
                        }
                        if (strpos($name, 'style') !== false && empty($styleData)) {
                            $styleData = $this->readLookupSheet($sheet);
                            $this->info("✓ 从 {$sheet->getTitle()} sheet 读取 style: " . count($styleData) . " 条");
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("⚠ 无法读取 sheet: " . $e->getMessage());
                }
            }

            // Step 3: 处理 SKU，提取最后一个 - 后的值并去重计数
            // 只统计第三个位置之后的值（从第三个分隔符后开始）
            $lastPartCount = [];
            foreach ($skuList as $sku) {
                $parts = explode('-', $sku);
                // 只统计第三个及之后的部分
                for ($i = 2; $i < count($parts); $i++) {
                    $part = trim($parts[$i]);
                    $lastPartCount[$part] = ($lastPartCount[$part] ?? 0) + 1;
                }
            }

            // 获取大于3次的值，但只保留"属性值"（纯数字、颜色词、尺码词等）
            $propertyKeywords = [
                // 颜色相关
                'Black', 'Blue', 'Red', 'White', 'Green', 'Pink', 'Purple', 'Yellow', 'Orange', 'Brown',
                'Gray', 'Grey', 'Gold', 'Silver', 'Navy', 'Beige', 'Lavender', 'Emerald',
                'Light', 'Dark', 'Forest', 'Light Blue', 'Dark Purple', 'Light Purple', 'Light Pink',
                'Blue+White', 'Pink+Red', 'Pink+Silver', 'Red+White',
                // 尺码相关
                'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL',
                // 服装类型（应该清理的）
                'Crewneck', 'Hoodie', 'T-shirt', 'T-Shirt', 'Tshirt', 'Shirt', 'Sweatshirt', 'Hoodie', 'Pajamas',
                'Blanket', 'Washed T',
                // 图案和装饰
                'Flower', 'Heart', 'Sunflower',
                // 数字（通常是尺寸编号）
            ];

            $frequentValues = array_keys(array_filter($lastPartCount, function ($count) use ($propertyKeywords) {
                return $count > 3;
            }));

            // 筛选频繁值，只保留"属性"性质的值
            $filteredFrequentValues = [];
            foreach ($frequentValues as $val) {
                // 如果是纯数字，保留
                if (is_numeric($val)) {
                    $filteredFrequentValues[] = $val;
                    continue;
                }
                // 如果包含属性关键词，保留
                foreach ($propertyKeywords as $keyword) {
                    if (stripos($val, $keyword) !== false) {
                        $filteredFrequentValues[] = $val;
                        break;
                    }
                }
            }

            $this->info("✓ 提取的属性高频值 (>3次): " . count($filteredFrequentValues));
            $this->line("属性值: " . implode(', ', array_slice($filteredFrequentValues, 0, 30)) . (count($filteredFrequentValues) > 30 ? '...' : ''));

            // 使用筛选后的频繁值
            $excludeValues = array_unique($filteredFrequentValues);

            // 排除数值类型的值，转换为字符串进行比较
            $excludeValues = array_map('strval', $excludeValues);

            $this->info("✓ 需要排除的属性值总数: " . count($excludeValues));

            // Step 4: 重新处理 SKU，去除包含的值
            // 重要：前两个值必须保留，只从第三个值开始检查排除
            $processedData = [];
            $skuDeduplicated = [];

            foreach ($allData as $item) {
                $sku = $item['sku'];
                $parts = explode('-', $sku);

                // 保留前两个值
                $keptParts = [];
                if (count($parts) >= 1) {
                    $keptParts[] = $parts[0];  // 第一个值，总是保留
                }
                if (count($parts) >= 2) {
                    $keptParts[] = $parts[1];  // 第二个值，总是保留
                }

                // 从第三个值开始处理
                if (count($parts) > 2) {
                    for ($i = 2; $i < count($parts); $i++) {
                        $part = trim($parts[$i]);

                        // 特殊处理：检查是否是 T-shirt 的一部分
                        // 如果当前是 T，下一个是 shirt/Shirt，则应该一起清理
                        $isTShirtPart = false;
                        if (strtolower($part) === 't' && $i + 1 < count($parts)) {
                            $nextPart = trim($parts[$i + 1]);
                            if (strtolower($nextPart) === 'shirt' || stripos($nextPart, 'shirt') !== false) {
                                $isTShirtPart = true;
                                // 跳过下一个 shirt 部分
                                $i++;
                            }
                        }

                        // 检查是否应该排除
                        $shouldExclude = $isTShirtPart;
                        if (!$shouldExclude) {
                            foreach ($excludeValues as $excludeValue) {
                                $excludeValue = strval($excludeValue);
                                // 使用大小写不敏感的字符串比较
                                if (stripos(strval($part), $excludeValue) !== false) {
                                    $shouldExclude = true;
                                    break;
                                }
                            }
                        }

                        // 只有不在排除值中的才保留
                        if (!$shouldExclude) {
                            $keptParts[] = $part;
                        }
                    }
                }

                // 重新拼接
                $cleanedSku = implode('-', $keptParts);

                // 如果被完全清空（不应该发生，因为前两个值总是保留），则保留原始值
                if (empty($cleanedSku)) {
                    $cleanedSku = $sku;
                }

                $processedItem = [
                    'original_sku' => $sku,
                    'cleaned_sku' => $cleanedSku,
                    'product_type' => $item['product_type'],
                    'chinese_name' => $item['chinese_name'],
                    'top_people_count' => $item['top_people_count'],
                ];

                // 按 cleaned_sku 去重（只保留第一条）
                if (!isset($skuDeduplicated[$cleanedSku])) {
                    $processedData[] = $processedItem;
                    $skuDeduplicated[$cleanedSku] = true;
                }
            }

            $this->info("✓ 处理完毕，去重后: " . count($processedData) . " 条记录");

            // Step 5: 保存为 JSON
            $outputPath = storage_path('app/private/sku-cleaned.json');
            $jsonContent = json_encode($processedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($outputPath, $jsonContent);

            $this->info("✓ JSON 已保存到: {$outputPath}");

            // Step 6: 同时保存排除值列表
            $excludeListPath = storage_path('app/private/sku-exclude-values.json');
            $excludeContent = json_encode([
                'frequent_values' => $frequentValues,
                'color_values' => array_keys($colorData),
                'style_values' => array_keys($styleData),
                'all_exclude_values' => $excludeValues,
                'total_exclude_count' => count($excludeValues),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($excludeListPath, $excludeContent);

            $this->info("✓ 排除值列表已保存到: {$excludeListPath}");

            // Step 7: 显示统计信息
            $this->line("\n========== 处理统计 ==========");
            $this->line("原始记录数: " . count($allData));
            $this->line("去重后: " . count($processedData));
            $this->line("删除的记录: " . (count($allData) - count($processedData)));
            $this->line("高频值 (>3次): " . count($frequentValues));
            $this->line("Color 值: " . count($colorData));
            $this->line("Style 值: " . count($styleData));
            $this->line("总排除值: " . count($excludeValues));
            $this->line("=============================\n");

            $this->info("✅ 处理完成！");

        } catch (\Exception $e) {
            $this->error("错误: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function readLookupSheet($sheet)
    {
        $data = [];
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $colA = trim($sheet->getCell('A' . $row)->getValue());
            $colB = trim($sheet->getCell('B' . $row)->getValue());

            if (empty($colA)) {
                continue;
            }

            $data[$colA] = $colB;
        }

        return $data;
    }
}
