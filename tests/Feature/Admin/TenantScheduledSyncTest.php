<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantScheduledSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas_applications.applications.udruga-saas' => [
                'driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
                'base_url' => 'http://udruga-saas.test',
                'api_key' => 'test-sync-key',
            ],
            'saas_applications.applications.other-saas' => [
                'driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
                'base_url' => 'http://other-saas.test',
                'api_key' => 'other-key',
            ],
        ]);
    }

    public function test_tenants_sync_command_syncs_all_configured_applications(): void
    {
        Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        Application::query()->create([
            'name' => 'Other SaaS',
            'slug' => 'other-saas',
            'description' => 'Test',
        ]);
        Application::query()->create([
            'name' => 'Unconfigured',
            'slug' => 'unconfigured',
            'description' => 'Test',
        ]);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'name' => 'Udruga A',
                        'slug' => 'udruga-a',
                        'status' => 'active',
                        'plan' => 'basic',
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
            'other-saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    [
                        'id' => 2,
                        'name' => 'Udruga B',
                        'slug' => 'udruga-b',
                        'status' => 'pending',
                        'plan' => 'standard',
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
        ]);

        $this->artisan('tenants:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenants', [
            'external_id' => '1',
            'name' => 'Udruga A',
        ]);
        $this->assertDatabaseHas('tenants', [
            'external_id' => '2',
            'name' => 'Udruga B',
        ]);
        $this->assertDatabaseHas('applications', [
            'slug' => 'udruga-saas',
        ]);
        $this->assertDatabaseHas('applications', [
            'slug' => 'other-saas',
        ]);

        $udrugaApp = Application::query()->where('slug', 'udruga-saas')->first();
        $otherApp = Application::query()->where('slug', 'other-saas')->first();
        $unconfiguredApp = Application::query()->where('slug', 'unconfigured')->first();

        $this->assertNotNull($udrugaApp->last_synced_at);
        $this->assertNotNull($otherApp->last_synced_at);
        $this->assertNull($unconfiguredApp->last_synced_at);
    }

    public function test_tenants_sync_command_can_target_single_application(): void
    {
        Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        Application::query()->create([
            'name' => 'Other SaaS',
            'slug' => 'other-saas',
            'description' => 'Test',
        ]);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    [
                        'id' => 9,
                        'name' => 'Samo ova',
                        'slug' => 'samo-ova',
                        'status' => 'active',
                        'plan' => 'basic',
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
        ]);

        $this->artisan('tenants:sync', ['slug' => 'udruga-saas'])
            ->assertSuccessful();

        $this->assertDatabaseHas('tenants', ['external_id' => '9']);
        $this->assertDatabaseMissing('tenants', ['external_id' => '2']);
    }

    public function test_manual_sync_updates_last_synced_at_on_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([
                'data' => [],
                'meta' => ['total' => 0],
            ]),
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.sync'))
            ->assertRedirect();

        $application->refresh();
        $this->assertNotNull($application->last_synced_at);

        $response = $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Zadnja sinkronizacija:');
        $response->assertSee($application->last_synced_at->format('d.m.Y. H:i'));
    }

    public function test_tenants_sync_command_fails_when_api_errors(): void
    {
        Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([], 500),
        ]);

        $this->artisan('tenants:sync')
            ->assertFailed();
    }
}
