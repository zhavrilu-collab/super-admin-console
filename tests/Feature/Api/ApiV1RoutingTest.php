<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\PlatformUserLink;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiV1RoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_v1_login_matches_legacy_login(): void
    {
        $user = User::factory()->create([
            'email' => 'v1-user@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
        ]);

        $legacy = $this->postJson('/api/auth/login', [
            'email' => 'v1-user@test.hr',
            'password' => 'password123',
        ]);

        $v1 = $this->postJson('/api/v1/auth/login', [
            'email' => 'v1-user@test.hr',
            'password' => 'password123',
        ]);

        $legacy->assertOk()->assertJsonPath('user.id', $user->id);
        $v1->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_legacy_api_responses_include_deprecation_header(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'missing@test.hr',
            'password' => 'wrong',
        ]);

        $response->assertHeader('Deprecation', 'true');
        $this->assertStringContainsString('/api/v1/auth/login', (string) $response->headers->get('Link'));
    }

    public function test_v1_openapi_document_is_available(): void
    {
        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.version', '1.0.0')
            ->assertJsonPath('paths./auth/login.post.summary', 'Platform login');
    }

    public function test_v1_workspace_endpoint_requires_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'workspace@test.hr',
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
            'external_user_id' => '9',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'workspace@test.hr',
            'password' => 'password123',
        ]);

        $token = $login->json('token');

        $this->getJson('/api/v1/platform/workspaces?application_slug=udruga-saas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonStructure(['workspaces']);
    }
}
