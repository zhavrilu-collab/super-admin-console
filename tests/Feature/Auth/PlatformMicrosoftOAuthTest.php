<?php

namespace Tests\Feature\Auth;

use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class PlatformMicrosoftOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.microsoft.client_id' => 'microsoft-client-id',
            'services.microsoft.client_secret' => 'microsoft-client-secret',
            'services.microsoft.redirect' => 'http://localhost/auth/microsoft/callback',
            'services.microsoft.tenant' => 'common',
        ]);
    }

    public function test_microsoft_redirect_starts_socialite_flow(): void
    {
        Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);

        Socialite::shouldReceive('driver->redirect')
            ->once()
            ->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));

        $this->get('/auth/microsoft/redirect?application_slug=udruga-saas&return_url=http://127.0.0.1:8000/auth/core/callback')
            ->assertRedirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize')
            ->assertSessionHas('platform_oauth.return_url', 'http://127.0.0.1:8000/auth/core/callback');
    }

    public function test_microsoft_callback_issues_token_and_links_identity(): void
    {
        $microsoftUser = new SocialiteUser;
        $microsoftUser->id = 'ms-user-456';
        $microsoftUser->email = 'ms-user@test.hr';
        $microsoftUser->name = 'MS User';

        Socialite::shouldReceive('driver->user')->once()->andReturn($microsoftUser);

        $response = $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/microsoft/callback');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('token=', $location);

        $this->assertDatabaseHas('users', [
            'email' => 'ms-user@test.hr',
        ]);

        $this->assertDatabaseHas('oauth_identities', [
            'provider' => 'microsoft',
            'provider_id' => 'ms-user-456',
        ]);
    }

    public function test_microsoft_callback_links_existing_platform_user(): void
    {
        $user = User::factory()->create([
            'email' => 'linked-ms@test.hr',
            'is_super_admin' => false,
        ]);

        $microsoftUser = new SocialiteUser;
        $microsoftUser->id = 'ms-linked-1';
        $microsoftUser->email = 'linked-ms@test.hr';
        $microsoftUser->name = 'Linked MS';

        Socialite::shouldReceive('driver->user')->once()->andReturn($microsoftUser);

        $this->withSession([
            'platform_oauth.return_url' => 'http://127.0.0.1:8000/auth/core/callback',
        ])->get('/auth/microsoft/callback')->assertRedirect();

        $this->assertDatabaseHas('oauth_identities', [
            'user_id' => $user->id,
            'provider' => 'microsoft',
            'provider_id' => 'ms-linked-1',
        ]);
    }
}
