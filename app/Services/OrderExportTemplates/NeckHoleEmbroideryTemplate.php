<?php

namespace App\Services\OrderExportTemplates;

class NeckHoleEmbroideryTemplate extends AbstractOrderExportTemplate
{
    public function key()
    {
        return 'neck_hole_embroidery';
    }

    public function label()
    {
        return '领口破洞刺绣';
    }

    public function supportedChineseNames()
    {
        return ['领口破洞刺绣'];
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
            '左袖符号',
            '左袖线色',
            '袖子位置',
            '右袖信息',
            '右袖符号',
            '备注',
            '右袖线色',
            '袖子位置',
            '领口信息',
            '领口文本颜色',
            '刺绣位置',
            '贺卡/礼品',
            '备注',
        ]);
    }

    protected function applyRules(array $values, array $row, array $context)
    {
        $attributes = $this->attributesAfter($row['product_specs'] ?? '', 0);
        $threadColor = $this->firstAttributeValue($attributes, ['thread color']);
        $text = $this->firstAttributeValue($attributes, ['text']);
        $collarEmbroidery = $this->firstAttributeValue($attributes, ['collar embroidery']);
        $embroideryPosition = $this->firstAttributeValue($attributes, ['embroidery', 'position']);
        $phrase = $this->firstExactAttributeValue($attributes, 'Phrase');
        $upperStitchColor = $this->firstExactAttributeValue($attributes, 'Upper Stitch Color');
        $lowerStitchColor = $this->firstExactAttributeValue($attributes, 'Lower Stitch Color');
        $colorLookup = $context['color_lookup'] ?? [];
        $colorTranslator = $context['color_translation_resolver'] ?? null;

        if ($phrase !== '') {
            $this->setHeaderValueIfBlank($values, '领口信息', $phrase);
        } elseif ($collarEmbroidery !== '') {
            $this->setHeaderValueIfBlank($values, '领口信息', $collarEmbroidery);
        } elseif ($text !== '') {
            $this->setHeaderValueIfBlank($values, '领口信息', $text);
        }

        $collarColorLines = [];
        if ($upperStitchColor !== '') {
            $collarColorLines[] = '上线颜色：' . $this->translateLookupValue($upperStitchColor, $colorLookup, $colorTranslator);
        }
        if ($lowerStitchColor !== '') {
            $collarColorLines[] = '下线颜色：' . $this->translateLookupValue($lowerStitchColor, $colorLookup, $colorTranslator);
        }

        if (!empty($collarColorLines)) {
            $this->setHeaderValue($values, '领口文本颜色', implode("\n", $collarColorLines));
        } elseif ($threadColor !== '') {
            $this->setHeaderValueIfBlank($values, '领口文本颜色', $this->translateLookupValue($threadColor, $colorLookup, $colorTranslator));
        }

        if ($embroideryPosition !== '') {
            $this->setHeaderValueIfBlank($values, '刺绣位置', $embroideryPosition);
        } else {
            $this->setHeaderValueIfBlank($values, '刺绣位置', '左领口');
        }

        if ($this->isQk0007($row)) {
            $this->applyQk0007CollegeTeamLogoRules($values, $attributes, $row, $context);
        }

        if ($this->isQk4010($row)) {
            $this->applyQk4010SchoolInitialsRules($values, $attributes);
        }

        $this->applySleevePositionRules($values);

        return $values;
    }

    private function applyQk4010SchoolInitialsRules(array &$values, array $attributes)
    {
        $schoolInitials = $this->firstExactAttributeValue($attributes, 'School Initials');
        $customText = $this->firstExactAttributeValue($attributes, 'Custom Text Unter The School Initials');
        $lines = [];

        if ($schoolInitials !== '') {
            $lines[] = '第一行：' . $schoolInitials;
        }

        if ($customText !== '') {
            $lines[] = '第二行：' . $customText;
        }

        if (!empty($lines)) {
            $this->setHeaderValue($values, '领口信息', implode("\n", $lines));
        }
    }

    private function applyQk0007CollegeTeamLogoRules(array &$values, array $attributes, array $row, array $context)
    {
        foreach ($attributes as $index => $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $lowerName = strtolower(trim((string) ($attribute['name'] ?? '')));
            $value = trim((string) ($attribute['value'] ?? ''));
            $lowerValue = strtolower($value);

            if ($value === '') {
                continue;
            }

            if (strpos($lowerValue, 'names with heart') !== false) {
                $values[15] = "\u{6587}\u{672C}\u{5728}\u{7231}\u{5FC3}\u{91CC}";
            }

            if (strcasecmp($name, 'Left Heart text') === 0) {
                $this->appendSleeveInfo($values, 'left', $value);
            } elseif (strcasecmp($name, 'Right Heart text') === 0) {
                $this->appendSleeveInfo($values, 'right', $value);
            }

            if (strpos($lowerName, 'upload your photo/logo') !== false && $this->sleeveTargetFromText($lowerName) === 'left') {
                $image = $this->resolveOptionImage($context, $row, $name, $value);
                $this->appendSleeveIcon($values, 'left', $image !== '' ? $image : $value);
            }

            if (strpos($lowerName, 'embroidery icon') === false) {
                continue;
            }

            $target = $this->sleeveTargetFromText($lowerName);
            if ($target === '') {
                continue;
            }

            if (strpos($lowerValue, 'add your name/text') !== false) {
                $nameText = $this->nameTextValueForSleeve($attributes, $target);

                if ($nameText !== '') {
                    $this->setSleeveInfo($values, $target, $nameText);
                }
            }

            if (strpos($lowerValue, 'college team logo') === false) {
                continue;
            }

            $teamName = $this->nextCollegeTeamLogoValue($attributes, $index);
            if ($teamName === '') {
                continue;
            }

            $logoPath = $this->resolveLogoImage($context, $teamName);
            if ($logoPath !== '') {
                $this->appendSleeveIcon($values, $target, $logoPath);
            }
        }
    }

    private function isQk0007(array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === 'CS-QK0007-CX' || strpos($sku, 'CS-QK0007-CX') !== false;
    }

    private function isQk4010(array $row)
    {
        $cleanedSku = strtoupper(trim((string) ($row['cleaned_sku'] ?? '')));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $cleanedSku === 'CS-QK4010-CX' || strpos($sku, 'CS-QK4010-CX') !== false;
    }

    private function applySleevePositionRules(array &$values)
    {
        if ($this->hasHeaderValue($values, '左袖信息') || $this->hasHeaderValue($values, '左袖符号')) {
            $values[12] = '左袖';
        }

        if ($this->hasHeaderValue($values, '右袖信息') || $this->hasHeaderValue($values, '右袖符号')) {
            $values[17] = '右袖';
        }
    }

    private function hasHeaderValue(array $values, $header)
    {
        $index = $this->headerIndex($header);

        return $index !== null && ($values[$index] ?? '') !== '';
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

    private function sleeveTargetFromText($lowerText)
    {
        if (strpos($lowerText, 'left') !== false && strpos($lowerText, 'sleeve') !== false) {
            return 'left';
        }

        if (strpos($lowerText, 'right') !== false && strpos($lowerText, 'sleeve') !== false) {
            return 'right';
        }

        return '';
    }

    private function nextCollegeTeamLogoValue(array $attributes, $currentIndex)
    {
        $next = $attributes[$currentIndex + 1] ?? null;
        if ($next === null) {
            return '';
        }

        $name = strtolower(trim((string) ($next['name'] ?? '')));
        if (strpos($name, 'choose your college team logo') === false) {
            return '';
        }

        return trim((string) ($next['value'] ?? ''));
    }

    private function nameTextValueForSleeve(array $attributes, $target)
    {
        foreach ($attributes as $attribute) {
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));

            if (strpos($name, 'enter name/text') === false) {
                continue;
            }

            if ($this->sleeveTargetFromText($name) === $target) {
                return trim((string) ($attribute['value'] ?? ''));
            }
        }

        return '';
    }

    private function resolveLogoImage(array $context, $teamName)
    {
        $resolver = $context['logo_lookup_resolver'] ?? null;

        if ($resolver === null || !method_exists($resolver, 'resolve')) {
            return '';
        }

        return (string) $resolver->resolve($teamName);
    }

    private function appendSleeveIcon(array &$values, $target, $value)
    {
        if ($target === 'left') {
            $this->appendFirstHeaderValue($values, ['左袖符号'], $value);
        } elseif ($target === 'right') {
            $this->appendFirstHeaderValue($values, ['右袖符号'], $value);
        }
    }

    private function setSleeveInfo(array &$values, $target, $value)
    {
        if ($target === 'left') {
            $this->setHeaderValue($values, '左袖信息', $value);
        } elseif ($target === 'right') {
            $this->setHeaderValue($values, '右袖信息', $value);
        }
    }

    private function appendSleeveInfo(array &$values, $target, $value)
    {
        if ($target === 'left') {
            $this->appendIndexedValue($values, 9, $value);
        } elseif ($target === 'right') {
            $this->appendIndexedValue($values, 13, $value);
        }
    }

    private function appendIndexedValue(array &$values, $index, $value)
    {
        if (($values[$index] ?? '') === '') {
            $values[$index] = $value;
        } else {
            $values[$index] .= "\n" . $value;
        }
    }
}
