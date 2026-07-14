<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\PlatformUserLink;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformUserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_imports_users_and_preserves_password_hash(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $hash = '$2y$12$abcdefghijklmnopqrstuv0123456789012345678901234567890';

        $response = $this->postJson('/api/platform/users/import', [
            'application_slug' => 'udruga-saas',
            'users' => [
                [
                    'external_id' => '7',
                    'name' => 'Admin Udruga',
                    'email' => 'admin@udruga.hr',
                    'password' => $hash,
                ],
            ],
        ], [
            'Authorization' => 'Bearer test-webhook-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('imported.0.external_id', '7')
            ->assertJsonPath('imported.0.email', 'admin@udruga.hr');

        $coreUserId = (int) $response->json('imported.0.core_user_id');

        $this->assertDatabaseHas('users', [
            'id' => $coreUserId,
            'email' => 'admin@udruga.hr',
            'is_super_admin' => false,
        ]);

        $storedHash = DB::table('users')->where('id', $coreUserId)->value('password');
        $this->assertSame($hash, $storedHash);

        $this->assertDatabaseHas('platform_user_links', [
            'application_id' => $application->id,
            'user_id' => $coreUserId,
            'external_user_id' => '7',
        ]);
    }

    public function test_import_updates_existing_user_by_email(): void
    {
        Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $existing = User::factory()->create([
            'email' => 'admin@udruga.hr',
            'is_super_admin' => false,
        ]);

        $this->postJson('/api/platform/users/import', [
            'application_slug' => 'udruga-saas',
            'users' => [
                [
                    'external_id' => '99',
                    'name' => 'Novi naziv',
                    'email' => 'admin@udruga.hr',
                    'password' => '$2y$12$abcdefghijklmnopqrstuv0123456789012345678901234567890',
                ],
            ],
        ], [
            'Authorization' => 'Bearer test-webhook-secret',
        ])->assertOk();

        $this->assertSame(1, User::query()->where('email', 'admin@udruga.hr')->count());
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'name' => 'Novi naziv',
        ]);
    }
}
