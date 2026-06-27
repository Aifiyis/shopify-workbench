<?php

namespace Database\Seeders;

use App\Services\BusinessDataBackfillService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BusinessAdminSetupSeeder extends Seeder
{
    public function run(BusinessDataBackfillService $service)
    {
        $result = $service->run();

        Log::info('Business admin setup completed.', $result);

        return $result;
    }
}
