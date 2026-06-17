<?php

namespace App\Services\OrderExportTemplates;

class PersonOutlineColorTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'person_outline_color';
    }

    public function label()
    {
        return '人物轮廓彩图';
    }

    public function supportedChineseNames()
    {
        return ['人物轮廓', '人物彩图', '人物轮廓彩图'];
    }

    public function headers()
    {
        return $this->withProductSpecsHeader([
            '导表日期',
            '订单号',
            '款式图',
            '是否做货',
            '是否发货',
            '款式',
            '衣服颜色',
            '尺码',
            '数量',
            '左袖信息',
            '左袖图标',
            '备注',
            '袖子位置',
            '右袖信息',
            '右袖图标',
            '袖子位置',
            '袖子绣线颜色',
            '胸口样式',
            '胸口信息',
            '胸部文本颜色',
            '图片轮廓颜色',
            '胸部位置',
            '后背位置',
            '全彩/轮廓',
            '胸口文本闪片颜色',
            '刺绣风格',
            '音乐播放器歌曲名字',
            '音乐播放器歌曲作者',
            '内衣裤颜色',
            '内衣裤全彩/轮廓',
            '身体轮廓颜色',
            '大腿位置',
            '是否给图片添加边框',
            '图片1',
            '图片1下方文字',
            '图片2',
            '图片3',
            '图片4',
            '图片5',
            '图片6',
            '备注',
            '贺卡',
            '礼品袋',
            '备注',
            '设计稿',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 3);
        $photo = $this->firstAttributeValue($attributes, ['photo']);

        if ($photo !== '') {
            $this->setHeaderValue($values, '图片1', $photo);
        }

        if (($row['chinese_name'] ?? '') === '人物轮廓') {
            $this->setHeaderValue($values, '全彩/轮廓', '轮廓');
        }

        if (($row['chinese_name'] ?? '') === '人物彩图') {
            $this->setHeaderValue($values, '全彩/轮廓', '全彩');
        }

        return $values;
    }
}
