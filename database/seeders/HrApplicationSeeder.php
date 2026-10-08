<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Admin\Sync\HrSaasSyncDriver;
use Illuminate\Database\Seeder;

class HrApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $baseUrl = (string) config('saas_applications.applications.hr-saas.base_url', 'http://127.0.0.1:8004');
        $apiKey = (string) config('saas_applications.applications.hr-saas.api_key', '');

        if ($apiKey === '') {
            $udruga = Application::query()->where('slug', 'udruga-saas')->first();
            $apiKey = (string) ($udruga?->api_sync_key ?: 'dev-sync-key-change-me');
        }

        $hrSaas = Application::query()->updateOrCreate(
            ['slug' => 'hr-saas'],
            [
                'name' => 'SuperSkyCrew',
                'description' => 'Multi-tenant SaaS za upravljanje ljudskim resursima i evidenciju radnog vremena.',
                'sync_driver' => HrSaasSyncDriver::class,
                'api_base_url' => $baseUrl,
                'api_sync_key' => $apiKey,
            ],
        );

        app(SubscriptionPlanService::class)->seedHrDefaults($hrSaas);
    }
}
