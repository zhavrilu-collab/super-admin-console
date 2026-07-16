<?php

namespace Tests\Feature\Auth;

use App\Models\Application;
use App\Models\PlatformTenantMembership;
use App\Models\PlatformUserLink;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformUnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_login_page_is_available(): void
    {
        $this->get('/platform/prijava')->assertOk()->assertSee('Jedinstvena prijava');
    }

    public function test_single_workspace_login_redirects_to_module_callback(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'slug' => 'demo-udruga',
            'name' => 'Demo udruga',
            'status' => 'active',
            'plan' => 'standard',
        ]);

        User::factory()->create([
            'email' => 'owner@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $user = User::query()->where('email', 'owner@test.hr')->firstOrFail();

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

        $response = $this->post('/platform/prijava', [
            'email' => 'owner@test.hr',
            'password' => 'password123',
            'application_slug' => 'udruga-saas',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8000/auth/core/callback?', $location);
        $this->assertStringContainsString('token=', $location);
    }

    public function test_multiple_workspaces_show_picker(): void
    {
        $udrugaApp = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        $smbApp = Application::query()->create([
            'slug' => 'smb-saas',
            'name' => 'SMB SaaS',
            'api_base_url' => 'http://127.0.0.1:8002',
        ]);

        $user = User::factory()->create([
            'email' => 'multi@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        foreach ([
            [$udrugaApp, '10', 'udruga-a'],
            [$smbApp, '20', 'firma-b'],
        ] as [$application, $externalId, $slug]) {
            $tenant = Tenant::query()->create([
                'application_id' => $application->id,
                'external_id' => $externalId,
                'slug' => $slug,
                'name' => strtoupper($slug),
                'status' => 'active',
                'plan' => 'basic',
            ]);

            PlatformUserLink::query()->create([
                'user_id' => $user->id,
                'application_id' => $application->id,
                'external_user_id' => $externalId,
            ]);

            PlatformTenantMembership::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role' => PlatformTenantMembership::ROLE_OWNER,
            ]);
        }

        $this->post('/platform/prijava', [
            'email' => 'multi@test.hr',
            'password' => 'password123',
        ])
            ->assertRedirect(route('platform.pick'));

        $this->get(route('platform.pick'))
            ->assertOk()
            ->assertSee('UDRUGA-A')
            ->assertSee('FIRMA-B');
    }

    public function test_workspace_pick_redirects_to_selected_module(): void
    {
        $udrugaApp = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        $smbApp = Application::query()->create([
            'slug' => 'smb-saas',
            'name' => 'SMB SaaS',
            'api_base_url' => 'http://127.0.0.1:8002',
        ]);

        $user = User::factory()->create([
            'email' => 'picker@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $udrugaTenant = Tenant::query()->create([
            'application_id' => $udrugaApp->id,
            'external_id' => '11',
            'slug' => 'udruga-pick',
            'name' => 'Udruga pick',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $smbTenant = Tenant::query()->create([
            'application_id' => $smbApp->id,
            'external_id' => '55',
            'slug' => 'demo-firma',
            'name' => 'Demo firma',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        foreach ([
            [$udrugaApp, $udrugaTenant, '11'],
            [$smbApp, $smbTenant, '55'],
        ] as [$application, $tenant, $externalUserId]) {
            PlatformUserLink::query()->create([
                'user_id' => $user->id,
                'application_id' => $application->id,
                'external_user_id' => $externalUserId,
            ]);

            PlatformTenantMembership::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role' => PlatformTenantMembership::ROLE_OWNER,
            ]);
        }

        $this->post('/platform/prijava', [
            'email' => 'picker@test.hr',
            'password' => 'password123',
        ])->assertRedirect(route('platform.pick'));

        $response = $this->post(route('platform.pick.store'), [
            'workspace_key' => 'smb-saas:55',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8002/auth/core/callback?', $location);
    }

    public function test_login_without_workspaces_shows_error(): void
    {
        User::factory()->create([
            'email' => 'lonely@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $this->from('/platform/prijava')
            ->post('/platform/prijava', [
                'email' => 'lonely@test.hr',
                'password' => 'password123',
            ])
            ->assertRedirect('/platform/prijava')
            ->assertSessionHasErrors('email');
    }
}
