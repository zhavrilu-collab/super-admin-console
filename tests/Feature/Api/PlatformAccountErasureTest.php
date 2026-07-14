<?php

namespace Tests\Feature\Api;

use App\Enums\AccountDeletionStatus;
use App\Models\AccountDeletionRequest;
use App\Models\Application;
use App\Models\PlatformAccessToken;
use App\Models\PlatformTenantMembership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlatformAccountErasureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gdpr.erasure_grace_period_days' => 14]);
    }

    public function test_user_can_request_account_deletion_with_grace_period(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $token = PlatformAccessToken::issueFor($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/account-deletion');

        $response->assertAccepted()
            ->assertJsonPath('deletion.status', 'pending')
            ->assertJsonPath('deletion.grace_period_days', 14);

        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'status' => AccountDeletionStatus::Pending->value,
        ]);

        $this->assertDatabaseMissing('platform_access_tokens', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_cancel_pending_deletion(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $token = PlatformAccessToken::issueFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/account-deletion')->assertAccepted();

        $newToken = PlatformAccessToken::issueFor($user);

        $this->withToken($newToken)
            ->deleteJson('/api/v1/auth/account-deletion')
            ->assertOk()
            ->assertJsonPath('deletion.status', 'none');

        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'status' => AccountDeletionStatus::Cancelled->value,
        ]);
    }

    public function test_sole_owner_cannot_request_deletion(): void
    {
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);

        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'org-1',
            'name' => 'Udruga Test',
            'slug' => 'udruga-test',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        PlatformTenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => PlatformTenantMembership::ROLE_OWNER,
        ]);

        $token = PlatformAccessToken::issueFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/account-deletion')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account']);
    }

    public function test_due_deletions_are_processed_by_command(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
            'email' => 'erase-me@test.hr',
        ]);

        AccountDeletionRequest::query()->create([
            'user_id' => $user->id,
            'status' => AccountDeletionStatus::Pending,
            'requested_at' => now()->subDays(15),
            'scheduled_deletion_at' => now()->subDay(),
        ]);

        Artisan::call('gdpr:process-account-deletions');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('account_deletion_requests', [
            'status' => AccountDeletionStatus::Completed->value,
        ]);
    }

    public function test_super_admin_cannot_use_platform_erasure_api(): void
    {
        $user = User::factory()->superAdmin()->create();
        $token = PlatformAccessToken::issueFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/account-deletion')
            ->assertForbidden();
    }
}
