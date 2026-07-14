<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\PlatformUserLink;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_platform_user_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'tenant-admin@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        PlatformUserLink::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'external_user_id' => '42',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'tenant-admin@test.hr',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.email', 'tenant-admin@test.hr')
            ->assertJsonPath('user.links.0.external_user_id', '42');

        $token = $login->json('token');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }

    public function test_super_admin_cannot_use_platform_login(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_super_admin' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
