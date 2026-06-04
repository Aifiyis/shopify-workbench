<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;
use PHPExcel;
use PHPExcel_IOFactory;

/**
 * Excel 导出服务
 * 按照 de_order_with_image_0203230600.xlsx 的格式导出订单数据
 */
class ExcelExportService
{
    private $columns = [
        'Order Date',
        'Order Name',
        'Product Title',
        'Product Type',
        'Multi-types',
        'Check Socks Num',
        'Quantity',
        '0203-73528-73587()',
        'Option 1',
        'Option 3',
        'Product Tags',
        'Picture URL',
        'Blank',
        'Pic Name',
        'Extra Details',
        'Custom Text',
        'Note',
    ];

    /**
     * 导出订单为 Excel 文件
     *
     * @param Collection $orders 订单集合
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return string Excel 文件路径
     */
    public function export(Collection $orders, $startDate, $endDate): string
    {
        $excel = new PHPExcel();
        $sheet = $excel->getActiveSheet();

        // 设置列标题
        $this->writeHeaders($sheet);

        // 写入数据
        $row = 2;
        foreach ($orders as $order) {
            foreach ($order->lineItems as $lineItem) {
                $this->writeOrderLine($sheet, $row, $order, $lineItem);
                $row++;
            }
        }

        // 调整列宽
        $this->adjustColumnWidths($sheet);

        // 保存文件
        $filename = "orders_{$startDate}_{$endDate}_" . time() . ".xlsx";
        $filepath = storage_path("exports/{$filename}");

        // 确保目录存在
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * 写入列标题
     *
     * @param PHPExcel_Worksheet $sheet
     * @return void
     */
    private function writeHeaders(&$sheet): void
    {
        foreach ($this->columns as $index => $header) {
            $column = chr(65 + $index); // A, B, C...
            $sheet->setCellValue("{$column}1", $header);

            // 设置标题样式
            $sheet->getStyle("{$column}1")->getFont()->setBold(true);
            $sheet->getStyle("{$column}1")->getFill()
                ->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D3D3D3');
        }
    }

    /**
     * 写入订单行
     *
     * @param PHPExcel_Worksheet $sheet
     * @param int $row 行号
     * @param Order $order 订单
     * @param OrderLineItem $lineItem 行项目
     * @return void
     */
    private function writeOrderLine(&$sheet, $row, $order, $lineItem): void
    {
        $data = [
            $order->order_date ? $order->order_date->format('Y-m-d H:i:s') : '',
            $order->order_name,
            $lineItem->product_title,
            $lineItem->product_type,
            $lineItem->multi_types,
            '',  // Check Socks Num - 保持空白或根据业务逻辑填充
            $lineItem->quantity,
            $lineItem->sku,
            $lineItem->option1,
            $lineItem->option3,
            $lineItem->product_tags,
            $lineItem->picture_url,
            '',  // Blank
            $lineItem->pic_name,
            $lineItem->extra_details,
            $lineItem->custom_text,
            '',  // Note
        ];

        // 按列写入数据
        foreach ($data as $index => $value) {
            $column = chr(65 + $index); // A, B, C...
            $sheet->setCellValue("{$column}{$row}", $value);
        }
    }

    /**
     * 调整列宽
     *
     * @param PHPExcel_Worksheet $sheet
     * @return void
     */
    private function adjustColumnWidths(&$sheet): void
    {
        $columnWidths = [
            'A' => 20, // Order Date
            'B' => 20, // Order Name
            'C' => 30, // Product Title
            'D' => 30, // Product Type
            'E' => 15, // Multi-types
            'F' => 15, // Check Socks Num
            'G' => 10, // Quantity
            'H' => 20, // SKU
            'I' => 20, // Option 1
            'J' => 20, // Option 3
            'K' => 30, // Product Tags
            'L' => 40, // Picture URL
            'M' => 10, // Blank
            'N' => 25, // Pic Name
            'O' => 40, // Extra Details
            'P' => 40, // Custom Text
            'Q' => 20, // Note
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * 获取导出文件的下载 URL
     *
     * @param string $filepath 文件路径
     * @return string 下载 URL
     */
    public function getDownloadUrl($filepath): string
    {
        $filename = basename($filepath);
        return route('export.download', ['filename' => $filename]);
    }
}
