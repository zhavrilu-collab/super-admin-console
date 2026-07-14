<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\PlatformInvite;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_module_can_create_and_accept_invite(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'slug' => 'demo-udruga',
            'name' => 'Demo udruga',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'email' => 'owner@test.hr',
            'password' => Hash::make('password123'),
        ]);

        $create = $this->postJson('/api/platform/invites', [
            'application_slug' => 'udruga-saas',
            'tenant_external_id' => '42',
            'email' => 'new-admin@test.hr',
            'role' => 'admin',
            'invited_by_email' => 'owner@test.hr',
        ], [
            'Authorization' => 'Bearer test-webhook-secret',
        ]);

        $create->assertCreated()
            ->assertJsonPath('invite.email', 'new-admin@test.hr')
            ->assertJsonPath('invite.role', 'admin');

        $token = $create->json('token');

        $this->getJson('/api/platform/invites/'.$token)
            ->assertOk()
            ->assertJsonPath('invite.tenant.external_id', '42');

        $accept = $this->postJson('/api/platform/invites/'.$token.'/accept', [
            'name' => 'Novi Admin',
            'password' => 'new-password-123',
        ]);

        $accept->assertOk()
            ->assertJsonPath('user.email', 'new-admin@test.hr')
            ->assertJsonPath('user.name', 'Novi Admin');

        $this->assertDatabaseHas('users', [
            'email' => 'new-admin@test.hr',
        ]);

        $this->assertDatabaseHas('platform_invites', [
            'tenant_id' => $tenant->id,
            'email' => 'new-admin@test.hr',
        ]);

        $invite = PlatformInvite::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invite?->accepted_at);

        $this->assertDatabaseHas('platform_tenant_memberships', [
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_module_can_list_pending_invites(): void
    {
        $application = Application::query()->create([
            'slug' => 'udruga-saas',
            'name' => 'Udruga SaaS',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '7',
            'slug' => 'udruga-7',
            'name' => 'Udruga 7',
            'status' => 'active',
        ]);

        PlatformInvite::issue($application, $tenant, 'pending@test.hr', PlatformInvite::ROLE_ADMIN);

        $this->getJson('/api/platform/invites?application_slug=udruga-saas&tenant_external_id=7', [
            'Authorization' => 'Bearer test-webhook-secret',
        ])
            ->assertOk()
            ->assertJsonCount(1, 'invites')
            ->assertJsonPath('invites.0.email', 'pending@test.hr');
    }
}
