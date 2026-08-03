<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Services\Admin\Sync\UdrugaSaasSyncDriver;
use App\Services\Admin\TenantSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantSyncDriverFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_falls_back_to_config_when_sync_driver_class_is_corrupt(): void
    {
        config([
            'saas_applications.applications.udruga-saas' => [
                'driver' => UdrugaSaasSyncDriver::class,
                'base_url' => 'https://app.test',
                'api_key' => 'secret',
            ],
        ]);

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
            // Corrupted class name (backslashes stripped) — previously made supports() false.
            'sync_driver' => 'AppServicesAdminSyncUdrugaSaasSyncDriver',
            'api_base_url' => 'https://app.test',
            'api_sync_key' => 'secret',
        ]);

        $sync = app(TenantSyncService::class);

        $this->assertTrue($sync->supports($application));
        $this->assertTrue($sync->isConfigured($application));
        $this->assertInstanceOf(UdrugaSaasSyncDriver::class, $sync->resolveDriver($application));
    }

    public function test_push_status_throws_when_not_configured_instead_of_silent_skip(): void
    {
        $application = Application::query()->create([
            'name' => 'Broken',
            'slug' => 'no-config-app',
            'description' => 'Test',
            'sync_driver' => null,
            'api_base_url' => null,
            'api_sync_key' => null,
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '1',
            'name' => 'Test',
            'slug' => 'test',
            'status' => TenantStatus::Pending,
            'plan' => 'basic',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nije konfigurirana');

        app(TenantSyncService::class)->pushStatus($tenant, TenantStatus::Active);
    }

    public function test_push_status_works_with_corrupt_driver_via_config_fallback(): void
    {
        Http::fake([
            'app.test/api/admin/organizations/12' => Http::response(['data' => ['id' => 12, 'status' => 'active']]),
        ]);

        config([
            'saas_applications.applications.udruga-saas' => [
                'driver' => UdrugaSaasSyncDriver::class,
                'base_url' => 'https://app.test',
                'api_key' => 'secret',
            ],
            'saas_applications.http_verify' => true,
            'saas_applications.http_resolve_loopback' => false,
        ]);

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
            'sync_driver' => 'AppServicesAdminSyncUdrugaSaasSyncDriver',
            'api_base_url' => 'https://app.test',
            'api_sync_key' => 'secret',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '12',
            'name' => 'Udruga',
            'slug' => 'udruga',
            'status' => TenantStatus::Pending,
            'plan' => 'standard',
        ]);

        app(TenantSyncService::class)->pushStatus($tenant, TenantStatus::Active);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.test/api/admin/organizations/12'
                && $request['status'] === 'active';
        });
    }
}
