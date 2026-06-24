<?php

namespace Tests\Unit\OrderExportTemplates;

use App\Services\OrderExportTemplates\TextEmbroideryTemplate;
use Tests\TestCase;

class TextEmbroideryTemplateTest extends TestCase
{
    public function test_qk2584_builds_chest_info_and_uses_dark_text_for_light_garment_colors()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-TEXT-QK2584-1',
            'sku' => 'RAW-CS-CX-QK2584',
            'cleaned_sku' => 'CS-CX-QK2584',
            'product_specs' => implode("\n", [
                'Choose Shirt Color: White',
                'Size: M',
                'Material: Cotton',
                'Enter Nickname: Hannah',
                'Customize The Cursive Text In The Middle Of Your Nickname: Mom',
                'Choose Thread Color: Red',
            ]),
        ], [
            'color_lookup' => [
                'White' => '白色',
                'Red' => '红色',
            ],
        ]);

        $this->assertSame("Hannah\n中间文本：Mom", $this->valueForHeader($template, $row, '胸口信息'));
        $this->assertSame("Hannah：黑色\n中间文本：红色", $this->valueForHeader($template, $row, '胸口文本颜色'));
    }

    public function test_qk2584_uses_white_text_for_other_garment_colors()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-TEXT-QK2584-2',
            'sku' => 'RAW-CS-CX-QK2584',
            'cleaned_sku' => 'CS-CX-QK2584',
            'product_specs' => implode("\n", [
                'Choose Shirt Color: Black',
                'Size: M',
                'Material: Cotton',
                'Enter Nickname: Hannah',
                'Customize The Cursive Text In The Middle Of Your Nickname: Mom',
                'Choose Thread Color: Blue',
            ]),
        ], [
            'color_lookup' => [
                'Black' => '黑色',
                'Blue' => '蓝色',
            ],
        ]);

        $this->assertSame("Hannah：白色\n中间文本：蓝色", $this->valueForHeader($template, $row, '胸口文本颜色'));
    }

    public function test_qk5322_builds_chest_info_from_nickname_and_year_values()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-TEXT-QK5322',
            'sku' => 'RAW-CS-QK5322-CX',
            'cleaned_sku' => 'CS-QK5322-CX',
            'product_specs' => implode("\n", [
                'Color: Gray',
                'Size: M',
                'Material: Cotton',
                'Nickname: Champs',
                'Custom Year: EST. 2024',
            ]),
        ], []);

        $this->assertSame("Champs\nEST. 2024", $this->valueForHeader($template, $row, '胸口信息'));
    }

    public function test_qk5874_builds_chest_info_from_title_and_text_under_title()
    {
        $template = new TextEmbroideryTemplate();

        $row = $template->mapRow([
            'filename_key' => '0601',
            'order_id' => 'ORDER-TEXT-QK5874',
            'sku' => 'RAW-CS-QK5874-CX',
            'cleaned_sku' => 'CS-QK5874-CX',
            'product_specs' => implode("\n", [
                'Color: Black',
                'Size: M',
                'Material: Cotton',
                'Custom Your Title: Sister',
                'Text Under The Title: Best Friend',
            ]),
        ], []);

        $this->assertSame("左上带心的字：Sister\n右下字：Best Friend", $this->valueForHeader($template, $row, '胸口信息'));
    }

    private function valueForHeader($template, array $row, $header)
    {
        $index = array_search($header, $template->headers(), true);

        $this->assertNotFalse($index, "Header {$header} should exist.");

        return $row[$index];
    }
}
