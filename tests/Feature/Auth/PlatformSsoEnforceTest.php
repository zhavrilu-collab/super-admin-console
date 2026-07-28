<?php

namespace Tests\Feature\Auth;

use App\Models\Application;
use App\Models\PlatformTenantMembership;
use App\Models\PlatformUserLink;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class PlatformSsoEnforceTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_blocked_when_only_sso_membership(): void
    {
        [$user] = $this->seedUserWithTenant(
            email: 'sso-only@test.hr',
            applicationSlug: 'udruga-saas',
            ssoEnforced: true,
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => 'sso-only@test.hr',
            'password' => 'password123',
            'application_slug' => 'udruga-saas',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->from('/platform/prijava')
            ->post('/platform/prijava', [
                'email' => 'sso-only@test.hr',
                'password' => 'password123',
                'application_slug' => 'udruga-saas',
            ])
            ->assertRedirect('/platform/prijava')
            ->assertSessionHasErrors('email');
    }

    public function test_password_allowed_with_mixed_memberships_without_application_slug(): void
    {
        $user = User::factory()->create([
            'email' => 'mixed@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $this->attachMembership($user, 'udruga-saas', 'sso-org', true);
        $this->attachMembership($user, 'smb-saas', 'password-org', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mixed@test.hr',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_password_blocked_when_application_slug_targets_sso_app(): void
    {
        $user = User::factory()->create([
            'email' => 'mixed-app@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $this->attachMembership($user, 'udruga-saas', 'sso-org', true);
        $this->attachMembership($user, 'smb-saas', 'password-org', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mixed-app@test.hr',
            'password' => 'password123',
            'application_slug' => 'udruga-saas',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mixed-app@test.hr',
            'password' => 'password123',
            'application_slug' => 'smb-saas',
        ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_oauth_still_works_for_sso_enforced_tenant_user(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $this->seedUserWithTenant(
            email: 'oauth-sso@test.hr',
            applicationSlug: 'udruga-saas',
            ssoEnforced: true,
            apiBaseUrl: 'http://127.0.0.1:8000',
        );

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-sso-1';
        $googleUser->email = 'oauth-sso@test.hr';
        $googleUser->name = 'OAuth SSO';

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $response = $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/google/callback');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8000/auth/core/callback?', $location);
        $this->assertStringContainsString('token=', $location);
    }

    public function test_workspace_payload_includes_sso_enforced(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'slug' => 'demo-tenant',
            'name' => 'Demo tenant',
            'status' => 'active',
            'plan' => 'standard',
            'sso_enforced' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'workspace-sso@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
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

        // Temporarily allow password so we can obtain a token.
        $tenant->forceFill(['sso_enforced' => false])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'workspace-sso@test.hr',
            'password' => 'password123',
        ])->assertOk();

        $tenant->forceFill(['sso_enforced' => true])->save();

        $this->getJson('/api/v1/platform/workspaces?application_slug=udruga-saas', [
            'Authorization' => 'Bearer '.$login->json('token'),
        ])
            ->assertOk()
            ->assertJsonPath('workspaces.0.tenant.sso_enforced', true);
    }

    /**
     * @return array{0: User, 1: Application, 2: Tenant}
     */
    private function seedUserWithTenant(
        string $email,
        string $applicationSlug,
        bool $ssoEnforced,
        string $apiBaseUrl = 'http://127.0.0.1:8000',
    ): array {
        $application = Application::query()->create([
            'slug' => $applicationSlug,
            'name' => strtoupper($applicationSlug),
            'api_base_url' => $apiBaseUrl,
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'slug' => 'demo-tenant',
            'name' => 'Demo tenant',
            'status' => 'active',
            'plan' => 'standard',
            'sso_enforced' => $ssoEnforced,
        ]);

        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
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

        return [$user, $application, $tenant];
    }

    private function attachMembership(
        User $user,
        string $applicationSlug,
        string $tenantSlug,
        bool $ssoEnforced,
    ): void {
        $application = Application::query()->firstOrCreate(
            ['slug' => $applicationSlug],
            [
                'name' => strtoupper($applicationSlug),
                'api_base_url' => 'http://127.0.0.1:8000',
            ],
        );

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => (string) fake()->unique()->numerify('###'),
            'slug' => $tenantSlug,
            'name' => strtoupper($tenantSlug),
            'status' => 'active',
            'plan' => 'basic',
            'sso_enforced' => $ssoEnforced,
        ]);

        PlatformUserLink::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'application_id' => $application->id,
            ],
            ['external_user_id' => (string) $user->id],
        );

        PlatformTenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => PlatformTenantMembership::ROLE_OWNER,
        ]);
    }
}
