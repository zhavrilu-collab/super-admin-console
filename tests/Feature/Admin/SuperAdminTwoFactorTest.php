<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\TwoFactorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SuperAdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_enable_two_factor_authentication(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->post(route('admin.two-factor.begin'))
            ->assertRedirect(route('admin.two-factor.index'));

        $secret = session(TwoFactorSession::SETUP_SECRET);
        $this->assertIsString($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->actingAs($user)
            ->post(route('admin.two-factor.confirm'), ['code' => $code]);

        $response->assertRedirect(route('admin.two-factor.index'));
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertIsArray(session(TwoFactorSession::FLASH_RECOVERY_CODES));
    }

    public function test_super_admin_with_two_factor_is_challenged_on_login(): void
    {
        $user = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->enableTwoFactor($user);

        $response = $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertSame($user->id, session(TwoFactorSession::LOGIN_USER_ID));
    }

    public function test_super_admin_can_complete_two_factor_login(): void
    {
        $user = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $secret = $this->enableTwoFactor($user);

        $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->post(route('two-factor.login.store'), [
            'code' => $code,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_two_factor_code_is_rejected(): void
    {
        $user = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->enableTwoFactor($user);

        $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), [
                'code' => '000000',
            ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_regular_user_login_is_not_affected_by_two_factor(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_without_two_factor_is_redirected_to_security_when_mandatory(): void
    {
        config(['security.require_super_admin_two_factor' => true]);

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.two-factor.index'));
        $response->assertSessionHas('warning');
    }

    public function test_super_admin_can_access_security_page_without_two_factor_when_mandatory(): void
    {
        config(['security.require_super_admin_two_factor' => true]);

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.two-factor.index'));

        $response->assertOk();
        $response->assertSee('obavezna', false);
    }

    public function test_super_admin_cannot_disable_two_factor_when_mandatory(): void
    {
        config(['security.require_super_admin_two_factor' => true]);

        $user = User::factory()->superAdmin()->create([
            'password' => 'password',
        ]);

        $secret = $this->enableTwoFactor($user);

        $response = $this->actingAs($user)->delete(route('admin.two-factor.destroy'), [
            'password' => 'password',
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);

        $response->assertForbidden();
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_super_admin_login_redirects_to_security_when_mandatory_and_two_factor_missing(): void
    {
        config(['security.require_super_admin_two_factor' => true]);

        $user = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.two-factor.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_enabling_two_factor_redirects_to_dashboard_when_mandatory(): void
    {
        config(['security.require_super_admin_two_factor' => true]);

        $user = User::factory()->superAdmin()->create([
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->post(route('admin.two-factor.begin'))
            ->assertRedirect(route('admin.two-factor.index'));

        $secret = session(TwoFactorSession::SETUP_SECRET);
        $this->assertIsString($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->actingAs($user)
            ->post(route('admin.two-factor.confirm'), ['code' => $code]);

        $response->assertRedirect(route('admin.dashboard'));
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
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
