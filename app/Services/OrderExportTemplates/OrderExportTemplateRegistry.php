<?php

namespace App\Services\OrderExportTemplates;

class OrderExportTemplateRegistry
{
    private $templatesByName = [];
    private $templatesByKey = [];

    public static function default()
    {
        return new self([
            new CtcxTemplate(),
            new NeckHoleEmbroideryTemplate(),
            new PetOutlineColorTemplate(),
            new PersonOutlineColorTemplate(),
            new BigNumberHeatTransferHoodieTemplate(),
            new HeatTransferClothingTemplate(),
            new StyleImageHeatTransferTemplate(),
            new TextEmbroideryTemplate(),
            new FoamHoodieTemplate(),
            new PatchworkHoodieTemplate(),
            new HeatTransferPantsTemplate(),
            new DigitalPrintTShirtTemplate(),
            new DigitalPrintSetTemplate(),
            new DigitalPrintHoodieTemplate(),
            new AppliqueEmbroideryTemplate(),
            new LineEmbroideryMomTemplate(),
            new ThreeDimensionalEmbroideryTemplate(),
            new DoubleSidedHoodieTemplate(),
            new TowelEmbroideryTemplate(),
            new HemBowEmbroideryTemplate(),
            new CarEmbroideryTemplate(),
            new DigitalPrintShortsTemplate(),
        ]);
    }

    public function __construct(array $templates)
    {
        foreach ($templates as $template) {
            $this->templatesByKey[$template->key()] = $template;

            foreach ($template->supportedChineseNames() as $name) {
                $name = trim((string) $name);

                if ($name !== '') {
                    $this->templatesByName[$name] = $template;
                }
            }
        }
    }

    public function forChineseName($name)
    {
        $name = trim((string) $name);

        return $this->templatesByName[$name] ?? null;
    }

    public function templates()
    {
        return array_values($this->templatesByKey);
    }
}
