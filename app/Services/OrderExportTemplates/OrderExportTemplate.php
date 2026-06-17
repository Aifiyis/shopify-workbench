<?php

namespace App\Services\OrderExportTemplates;

interface OrderExportTemplate
{
    public function key();

    public function label();

    public function supportedChineseNames();

    public function headers();

    public function mapRow(array $row, array $context);
}
