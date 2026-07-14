<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\PlatformTenantMembership;
use App\Models\PlatformUserLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_user_can_list_workspaces_from_platform_token(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'slug' => 'demo-udruga',
            'name' => 'Demo udruga',
            'status' => 'active',
            'plan' => 'standard',
        ]);

        $user = User::factory()->create([
            'email' => 'owner@test.hr',
            'password' => Hash::make('password123'),
        ]);

        PlatformUserLink::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'external_user_id' => '7',
        ]);

        PlatformTenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => PlatformTenantMembership::ROLE_OWNER,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'owner@test.hr',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('token');

        $this->getJson('/api/platform/workspaces?application_slug=udruga-saas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('workspaces.0.role', 'owner')
            ->assertJsonPath('workspaces.0.tenant.external_id', '42')
            ->assertJsonPath('workspaces.0.tenant.slug', 'demo-udruga');
    }

    public function test_module_can_sync_memberships(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '99',
            'slug' => 'sync-udruga',
            'name' => 'Sync udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $user = User::factory()->create(['email' => 'admin@test.hr']);

        PlatformUserLink::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'external_user_id' => '15',
        ]);

        $this->postJson('/api/platform/memberships/sync', [
            'application_slug' => 'udruga-saas',
            'memberships' => [[
                'external_user_id' => '15',
                'tenant_external_id' => '99',
                'role' => 'admin',
            ]],
        ], [
            'Authorization' => 'Bearer test-webhook-secret',
        ])
            ->assertOk()
            ->assertJsonPath('synced.0.role', 'admin');

        $this->assertDatabaseHas('platform_tenant_memberships', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
    }
}
