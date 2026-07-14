<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\ImpersonationSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->setMany([
            ConsoleSettingsService::WEBHOOK_SECRET => 'test-webhook-secret',
        ]);
    }

    public function test_module_can_start_and_end_impersonation_session(): void
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

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'is_super_admin' => true,
        ]);

        $issued = ImpersonationSession::issue($admin, $tenant, 'Support ticket #1');
        $token = $issued['plain_token'];

        $this->getJson('/api/platform/impersonation/'.$token)
            ->assertOk()
            ->assertJsonPath('session.tenant.slug', 'demo-udruga')
            ->assertJsonPath('session.admin.email', 'admin@example.com');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.started',
            'subject_id' => $tenant->id,
        ]);

        $this->postJson('/api/platform/impersonation/'.$token.'/end')
            ->assertOk();

        $this->assertNotNull($issued['session']->fresh()->ended_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.ended',
            'subject_id' => $tenant->id,
        ]);
    }
}
