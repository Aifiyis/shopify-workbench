<?php

namespace Database\Seeders;

use App\Services\SkuMatchProductTypeImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class SkuMatchProductTypeSeeder extends Seeder
{
    private $importService;

    public function __construct(SkuMatchProductTypeImportService $importService)
    {
        $this->importService = $importService;
    }

    public function run()
    {
        $result = $this->importService->import();

        Log::info('SKU product type import completed.', $result);

        if ($this->command) {
            $this->command->info('SKU product type import completed.');

            foreach ($result as $name => $value) {
                $this->command->line($name . ': ' . $value);
            }
        }

        return $result;
    }
}
