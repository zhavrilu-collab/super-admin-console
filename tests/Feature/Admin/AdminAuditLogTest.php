<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    private User $superAdmin;

    private Application $activeApplication;

    private Application $otherApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
        ]);
        $this->activeApplication = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->otherApplication = Application::query()->create([
            'name' => 'Other SaaS',
            'slug' => 'other-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($this->activeApplication);
        $this->seedSubscriptionPlans($this->otherApplication);
    }

    public function test_status_change_creates_audit_log_entry(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Pending);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-status', $tenant), [
                'status' => TenantStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->superAdmin->id,
            'application_id' => $this->activeApplication->id,
            'action' => AuditAction::TenantStatusChanged->value,
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
        ]);

        $log = AuditLog::query()->first();
        $this->assertSame('pending', $log->properties['from']);
        $this->assertSame('active', $log->properties['to']);
        $this->assertSame($tenant->name, $log->properties['tenant_name']);
    }

    public function test_plan_change_creates_audit_log_entry(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Active, 'basic');

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-plan', $tenant), [
                'plan' => 'standard',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->superAdmin->id,
            'application_id' => $this->activeApplication->id,
            'action' => AuditAction::TenantPlanChanged->value,
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
        ]);

        $log = AuditLog::query()->first();
        $this->assertSame('basic', $log->properties['from']);
        $this->assertSame('standard', $log->properties['to']);
    }

    public function test_audit_log_page_shows_entries_for_active_application_only(): void
    {
        $activeTenant = $this->createTenant($this->activeApplication, TenantStatus::Pending);
        $otherTenant = $this->createTenant($this->otherApplication, TenantStatus::Pending);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-status', $activeTenant), [
                'status' => TenantStatus::Active->value,
            ]);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->otherApplication->id])
            ->patch(route('admin.tenants.update-status', $otherTenant), [
                'status' => TenantStatus::Active->value,
            ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee($activeTenant->name);
        $response->assertDontSee($otherTenant->name);
        $response->assertSee('Super Admin');
        $response->assertSee('Promjena statusa tenanta');
    }

    public function test_non_super_admin_cannot_access_audit_log_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }

    private function createTenant(
        Application $application,
        TenantStatus $status,
        string $plan = 'basic',
    ): Tenant {
        return Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-'.fake()->unique()->numerify('###'),
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => $status,
            'plan' => $plan,
        ]);
    }
}
