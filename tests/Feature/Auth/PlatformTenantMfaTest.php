<?php

namespace Tests\Feature\Auth;

use App\Models\Application;
use App\Models\PlatformTenantMembership;
use App\Models\PlatformUserLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\PlatformLoginSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformTenantMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_login_challenges_when_tenant_mfa_enabled(): void
    {
        [$user] = $this->seedWorkspaceOwner('mfa@test.hr');
        $secret = $this->enableTwoFactor($user);

        $this->post('/platform/prijava', [
            'email' => 'mfa@test.hr',
            'password' => 'password123',
            'application_slug' => 'udruga-saas',
        ])->assertRedirect(route('platform.two-factor.login'));

        $this->assertTrue(session()->has(PlatformLoginSession::PENDING_USER_ID));

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->post(route('platform.two-factor.login.store'), [
            'code' => $code,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8000/auth/core/callback?', $location);
        $this->assertStringContainsString('token=', $location);
    }

    public function test_invalid_platform_mfa_code_is_rejected(): void
    {
        [$user] = $this->seedWorkspaceOwner('badcode@test.hr');
        $this->enableTwoFactor($user);

        $this->post('/platform/prijava', [
            'email' => 'badcode@test.hr',
            'password' => 'password123',
        ])->assertRedirect(route('platform.two-factor.login'));

        $this->from(route('platform.two-factor.login'))
            ->post(route('platform.two-factor.login.store'), [
                'code' => '000000',
            ])
            ->assertRedirect(route('platform.two-factor.login'))
            ->assertSessionHasErrors('code');
    }

    public function test_api_login_requires_two_factor_challenge(): void
    {
        [$user] = $this->seedWorkspaceOwner('api-mfa@test.hr');
        $secret = $this->enableTwoFactor($user);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'api-mfa@test.hr',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['two_factor_token', 'challenge_url']);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/two-factor', [
            'two_factor_token' => $login->json('two_factor_token'),
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_platform_user_can_enable_two_factor_via_api(): void
    {
        [$user] = $this->seedWorkspaceOwner('setup-mfa@test.hr');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'setup-mfa@test.hr',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('token');

        $begin = $this->postJson('/api/v1/auth/two-factor/begin', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg']);

        $secret = $begin->json('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/two-factor/confirm', [
            'secret' => $secret,
            'code' => $code,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true)
            ->assertJsonStructure(['recovery_codes']);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_oauth_callback_challenges_when_mfa_enabled(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        [$user] = $this->seedWorkspaceOwner('oauth-mfa@test.hr');
        $this->enableTwoFactor($user);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-mfa-1';
        $googleUser->email = 'oauth-mfa@test.hr';
        $googleUser->name = 'OAuth MFA';

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('platform.two-factor.login'));

        $this->assertTrue(session()->has(PlatformLoginSession::PENDING_USER_ID));
        $this->assertSame(
            'http://127.0.0.1:8000/auth/core/callback',
            session(PlatformLoginSession::PENDING_RETURN_URL),
        );
    }

    /**
     * @return array{0: User, 1: Application, 2: Tenant}
     */
    private function seedWorkspaceOwner(string $email): array
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

    private function enableTwoFactor(User $user): string
    {
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $service->enable($user, $secret, $code);
        $user->refresh();

        return $secret;
    }
}
