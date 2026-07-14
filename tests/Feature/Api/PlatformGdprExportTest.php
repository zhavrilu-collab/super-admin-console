<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\GdprExportLog;
use App\Models\OAuthIdentity;
use App\Models\PlatformAccessToken;
use App\Models\PlatformTenantMembership;
use App\Models\PlatformUserLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformGdprExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gdpr.max_exports_per_hour' => 3]);

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_authenticated_user_can_download_json_gdpr_export(): void
    {
        $user = $this->seedExportUser();

        $token = PlatformAccessToken::issueFor($user);

        $response = $this->get('/api/auth/data-export?format=json', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $json = json_decode($response->getContent(), true);
        $this->assertSame('gdpr-user@test.hr', $json['profile']['email']);
        $this->assertSame('15', $json['application_links'][0]['external_user_id']);
        $this->assertSame('owner', $json['workspaces'][0]['role']);
        $this->assertSame('google', $json['oauth_identities'][0]['provider']);

        $this->assertDatabaseHas('gdpr_export_logs', [
            'user_id' => $user->id,
            'format' => 'json',
        ]);
    }

    public function test_inline_json_export_is_available_for_module_merge(): void
    {
        $user = $this->seedExportUser();
        $token = PlatformAccessToken::issueFor($user);

        $this->getJson('/api/auth/data-export?format=json&inline=1', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.scope', 'platform');
    }

    public function test_csv_export_returns_zip_attachment(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive nije dostupan.');
        }

        $user = $this->seedExportUser();
        $token = PlatformAccessToken::issueFor($user);

        $response = $this->get('/api/auth/data-export?format=csv', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/zip', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_rate_limits_excessive_export_requests(): void
    {
        $user = User::factory()->create([
            'email' => 'rate-limit@test.hr',
            'is_super_admin' => false,
        ]);

        foreach (range(1, 3) as $index) {
            GdprExportLog::query()->create([
                'user_id' => $user->id,
                'format' => 'json',
                'scope' => 'platform',
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $token = PlatformAccessToken::issueFor($user);

        $this->getJson('/api/auth/data-export?format=json', [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(429);
    }

    public function test_super_admin_cannot_use_platform_gdpr_export(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'is_super_admin' => true,
        ]);

        $token = PlatformAccessToken::issueFor($admin);

        $this->getJson('/api/auth/data-export?format=json', [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    }

    private function seedExportUser(): User
    {
        $user = User::factory()->create([
            'email' => 'gdpr-user@test.hr',
            'password' => Hash::make('password123'),
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);

        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        PlatformUserLink::query()->create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'external_user_id' => '15',
        ]);

        OAuthIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-gdpr-1',
            'provider_email' => 'gdpr-user@test.hr',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '2',
            'name' => 'Test udruga',
            'slug' => 'test-udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        PlatformTenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        PlatformAccessToken::issueFor($user, 'existing-session');

        return $user;
    }
}
