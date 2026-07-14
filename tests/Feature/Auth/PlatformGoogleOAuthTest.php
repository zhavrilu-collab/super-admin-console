<?php

namespace Tests\Feature\Auth;

use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class PlatformGoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_google_redirect_rejects_foreign_return_url(): void
    {
        Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        $this->from('/')
            ->get('/auth/google/redirect?application_slug=udruga-saas&return_url=http://evil.test/callback')
            ->assertRedirect('/')
            ->assertSessionHasErrors('return_url');
    }

    public function test_google_redirect_starts_socialite_flow(): void
    {
        Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        Socialite::shouldReceive('driver->redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        $this->get('/auth/google/redirect?application_slug=udruga-saas&return_url=http://127.0.0.1:8000/auth/core/callback')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
            ->assertSessionHas('platform_oauth.return_url', 'http://127.0.0.1:8000/auth/core/callback');
    }

    public function test_google_callback_issues_token_and_redirects_to_module(): void
    {
        Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-user-123';
        $googleUser->email = 'oauth-user@test.hr';
        $googleUser->name = 'OAuth User';

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $response = $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/google/callback');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8000/auth/core/callback?', $location);
        $this->assertStringContainsString('token=', $location);

        $this->assertDatabaseHas('users', [
            'email' => 'oauth-user@test.hr',
            'is_super_admin' => false,
        ]);

        $this->assertDatabaseHas('oauth_identities', [
            'provider' => 'google',
            'provider_id' => 'google-user-123',
        ]);
    }

    public function test_google_callback_links_existing_platform_user(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-linked-1';
        $googleUser->email = 'linked@test.hr';
        $googleUser->name = 'Linked User';

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/google/callback')->assertRedirect();

        $this->assertDatabaseHas('oauth_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-linked-1',
        ]);
    }

    public function test_google_callback_rejects_super_admin_email(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'is_super_admin' => true,
        ]);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-admin-1';
        $googleUser->email = 'admin@example.com';
        $googleUser->name = 'Admin';

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $response = $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/google/callback');

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('oauth_error=', $location);
        $this->assertDatabaseMissing('oauth_identities', [
            'provider_id' => 'google-admin-1',
        ]);
    }
}
