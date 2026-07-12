<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Services\Admin\SubscriptionPlanService;
use Illuminate\Database\Seeder;

class SubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(SubscriptionPlanService::class);

        Application::query()
            ->orderBy('name')
            ->each(static function (Application $application) use ($service): void {
                $service->seedDefaults($application);
            });
    }
}
