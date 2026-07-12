<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\Sync\UdrugaSaasSyncDriver;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class TenantPlanSyncTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_plan_change_recovers_stale_external_id_by_slug(): void
    {
        Http::fake([
            'http://saas.test/api/admin/organizations/3' => Http::response(['message' => 'Not found'], 404),
            'http://saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    ['id' => 2, 'slug' => 'udruga-siletici-1', 'name' => 'Test', 'status' => 'active', 'plan' => 'basic'],
                ],
            ], 200),
            'http://saas.test/api/admin/organizations/2' => Http::response([
                'data' => ['id' => 2, 'slug' => 'udruga-siletici-1', 'status' => 'active', 'plan' => 'premium'],
            ], 200),
        ]);

        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
            'sync_driver' => UdrugaSaasSyncDriver::class,
            'api_base_url' => 'http://saas.test',
            'api_sync_key' => 'test-key',
        ]);
        $this->seedSubscriptionPlans($application);

        $staleTenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '2',
            'name' => 'Stari zapis',
            'slug' => 'udruga-siletici',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '3',
            'name' => 'Udruga Šiletići',
            'slug' => 'udruga-siletici-1',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->patch(route('admin.tenants.update-plan', $tenant), [
                'plan' => 'premium',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $tenant->refresh();
        $staleTenant->refresh();

        $this->assertSame('2', $tenant->external_id);
        $this->assertSame('premium', $tenant->plan);
        $this->assertNull($staleTenant->external_id);
    }

    public function test_plan_change_shows_warning_when_sync_fails(): void
    {
        Http::fake([
            'http://saas.test/api/admin/organizations/*' => Http::response(['message' => 'Not found'], 404),
            'http://saas.test/api/admin/organizations' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
            'sync_driver' => UdrugaSaasSyncDriver::class,
            'api_base_url' => 'http://saas.test',
            'api_sync_key' => 'test-key',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '999',
            'name' => 'Nepostojeća',
            'slug' => 'nepostojeca',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->patch(route('admin.tenants.update-plan', $tenant), [
                'plan' => 'premium',
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan' => 'basic',
        ]);
    }
}
