<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantSyncTest extends TestCase
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
        ]);
    }

    public function test_pull_sync_upserts_tenants_from_saas_api(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    [
                        'id' => 42,
                        'name' => 'Stvarna udruga',
                        'slug' => 'stvarna-udruga',
                        'status' => 'active',
                        'plan' => 'standard',
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
        ]);

        $response = $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.sync'));

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'application_id' => $application->id,
            'external_id' => '42',
            'name' => 'Stvarna udruga',
            'slug' => 'stvarna-udruga',
            'status' => TenantStatus::Active->value,
            'plan' => 'standard',
        ]);
    }

    public function test_status_change_pushes_to_saas_api(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '7',
            'name' => 'Push test',
            'slug' => 'push-test',
            'status' => TenantStatus::Pending,
            'plan' => 'basic',
        ]);

        Http::fake([
            'udruga-saas.test/api/admin/organizations/7' => Http::response([
                'data' => [
                    'id' => 7,
                    'status' => 'active',
                    'plan' => 'basic',
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->patch(route('admin.tenants.update-status', $tenant), [
                'status' => TenantStatus::Active->value,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'http://udruga-saas.test/api/admin/organizations/7'
                && $request->method() === 'PATCH'
                && $request['status'] === 'active';
        });
    }
}
