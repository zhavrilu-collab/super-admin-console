<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Admin\Sync\LegalSaasSyncDriver;
use Illuminate\Database\Seeder;

class LegalApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $baseUrl = (string) config('saas_applications.applications.legal-saas.base_url', 'http://127.0.0.1:8006');
        $apiKey = (string) config('saas_applications.applications.legal-saas.api_key', '');

        if ($apiKey === '') {
            $udruga = Application::query()->where('slug', 'udruga-saas')->first();
            $apiKey = (string) ($udruga?->api_sync_key ?: 'dev-sync-key-change-me');
        }

        $legalSaas = Application::query()->updateOrCreate(
            ['slug' => 'legal-saas'],
            [
                'name' => 'SuperSkyLaw',
                'description' => 'Multi-tenant SaaS za upravljanje odvjetničkim uredom.',
                'sync_driver' => LegalSaasSyncDriver::class,
                'api_base_url' => $baseUrl,
                'api_sync_key' => $apiKey,
            ],
        );

        app(SubscriptionPlanService::class)->seedLegalDefaults($legalSaas);
    }
}
