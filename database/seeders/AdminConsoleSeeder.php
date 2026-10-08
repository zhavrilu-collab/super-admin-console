<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Admin\SubscriptionPlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AdminConsoleSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::MAIL_MAILER => 'log',
            ConsoleSettingsService::MAIL_FROM_ADDRESS => 'noreply@admin.local',
            ConsoleSettingsService::MAIL_FROM_NAME => 'SuperSkyControl',
            ConsoleSettingsService::WEBHOOK_SECRET => 'dev-sync-key-change-me',
        ]);

        $udrugaSaas = Application::query()->updateOrCreate(
            ['slug' => 'udruga-saas'],
            [
                'name' => 'SuperSkyClub',
                'description' => 'Multi-tenant SaaS platforma za udruge.',
                'sync_driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
                'api_base_url' => 'http://127.0.0.1:8000',
                'api_sync_key' => 'dev-sync-key-change-me',
            ],
        );

        app(SubscriptionPlanService::class)->seedDefaults($udrugaSaas);

        $hrSaas = Application::query()->updateOrCreate(
            ['slug' => 'hr-saas'],
            [
                'name' => 'SuperSkyCrew',
                'description' => 'Multi-tenant SaaS za upravljanje ljudskim resursima i evidenciju radnog vremena.',
                'sync_driver' => \App\Services\Admin\Sync\HrSaasSyncDriver::class,
                'api_base_url' => (string) config('saas_applications.applications.hr-saas.base_url', 'http://127.0.0.1:8004'),
                'api_sync_key' => (string) (config('saas_applications.applications.hr-saas.api_key') ?: 'dev-sync-key-change-me'),
            ],
        );

        app(SubscriptionPlanService::class)->seedHrDefaults($hrSaas);

        $legalSaas = Application::query()->updateOrCreate(
            ['slug' => 'legal-saas'],
            [
                'name' => 'SuperSkyLaw',
                'description' => 'Multi-tenant SaaS za upravljanje odvjetničkim uredom.',
                'sync_driver' => \App\Services\Admin\Sync\LegalSaasSyncDriver::class,
                'api_base_url' => (string) config('saas_applications.applications.legal-saas.base_url', 'http://127.0.0.1:8006'),
                'api_sync_key' => (string) (config('saas_applications.applications.legal-saas.api_key') ?: 'dev-sync-key-change-me'),
            ],
        );

        app(SubscriptionPlanService::class)->seedLegalDefaults($legalSaas);

        $tenants = [
            ['external_id' => 'org-001', 'name' => 'Športski klub Zagreb', 'slug' => 'sk-zagreb', 'status' => TenantStatus::Active, 'plan' => 'standard'],
            ['external_id' => 'org-002', 'name' => 'Kulturno umjetničko društvo Split', 'slug' => 'kud-split', 'status' => TenantStatus::Active, 'plan' => 'premium'],
            ['external_id' => 'org-003', 'name' => 'Planinarsko društvo Velebit', 'slug' => 'pd-velebit', 'status' => TenantStatus::Pending, 'plan' => 'basic'],
            ['external_id' => 'org-004', 'name' => 'Udruga roditelja Osijek', 'slug' => 'ur-osijek', 'status' => TenantStatus::Active, 'plan' => 'basic'],
            ['external_id' => 'org-005', 'name' => 'Glazbena udruga Rijeka', 'slug' => 'gu-rijeka', 'status' => TenantStatus::Suspended, 'plan' => 'standard'],
            ['external_id' => 'org-006', 'name' => 'Eko udruga Zeleni korak', 'slug' => 'eko-zeleni-korak', 'status' => TenantStatus::Active, 'plan' => 'basic'],
            ['external_id' => 'org-007', 'name' => 'Udruga za zaštitu životinja', 'slug' => 'uzz-zivotinje', 'status' => TenantStatus::Pending, 'plan' => 'basic'],
            ['external_id' => 'org-008', 'name' => 'Vatrogasna zajednica Karlovac', 'slug' => 'vz-karlovac', 'status' => TenantStatus::Active, 'plan' => 'premium'],
            ['external_id' => 'org-009', 'name' => 'Udruga invalida rada', 'slug' => 'uir-pula', 'status' => TenantStatus::Active, 'plan' => 'standard'],
            ['external_id' => 'org-010', 'name' => 'Maticni odbor Dubrovnik', 'slug' => 'mo-dubrovnik', 'status' => TenantStatus::Suspended, 'plan' => 'basic'],
            ['external_id' => 'org-011', 'name' => 'Udruga mladih inovatora', 'slug' => 'umi-zadar', 'status' => TenantStatus::Pending, 'plan' => 'standard'],
            ['external_id' => 'org-012', 'name' => 'Fotoklub Svetlost', 'slug' => 'fotoklub-svetlost', 'status' => TenantStatus::Active, 'plan' => 'basic'],
        ];

        foreach ($tenants as $index => $tenantData) {
            Tenant::query()->updateOrCreate(
                [
                    'application_id' => $udrugaSaas->id,
                    'slug' => $tenantData['slug'],
                ],
                [
                    'external_id' => $tenantData['external_id'],
                    'name' => $tenantData['name'],
                    'status' => $tenantData['status'],
                    'plan' => $tenantData['plan'],
                    'synced_at' => Carbon::now()->subHours($index + 1),
                ],
            );
        }
    }
}
