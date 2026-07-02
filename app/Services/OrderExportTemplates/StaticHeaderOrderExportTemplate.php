<?php

namespace App\Services\OrderExportTemplates;

abstract class StaticHeaderOrderExportTemplate extends AbstractOrderExportTemplate
{
    protected $templateKey;
    protected $templateLabel;
    protected $chineseNames = [];
    protected $templateHeaders = [];

    public function key()
    {
        return $this->templateKey;
    }

    public function label()
    {
        return $this->templateLabel;
    }

    public function supportedChineseNames()
    {
        return $this->chineseNames;
    }

    public function headers()
    {
        return $this->withProductSpecsHeader($this->templateHeaders);
    }
}
