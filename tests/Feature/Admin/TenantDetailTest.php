<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class TenantDetailTest extends TestCase
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
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);
        $this->otherApplication = Application::query()->create([
            'name' => 'Other SaaS',
            'slug' => 'other-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($this->activeApplication);
        $this->seedSubscriptionPlans($this->otherApplication);
    }

    public function test_super_admin_can_view_tenant_detail_page(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Pending, 'standard');

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->get(route('admin.tenants.show', $tenant))
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee($tenant->slug)
            ->assertSee('Povijest moderacije')
            ->assertSee('Otvori u SaaS aplikaciji')
            ->assertSee('http://127.0.0.1:8000/'.$tenant->slug);
    }

    public function test_tenant_detail_shows_audit_history(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Pending);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-status', $tenant), [
                'status' => TenantStatus::Active->value,
            ]);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->get(route('admin.tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Promjena statusa tenanta')
            ->assertSee('Na čekanju → Aktivan');
    }

    public function test_status_change_from_detail_page_stays_on_detail_page(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Pending);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->from(route('admin.tenants.show', $tenant))
            ->patch(route('admin.tenants.update-status', $tenant), [
                'status' => TenantStatus::Active->value,
            ])
            ->assertRedirect(route('admin.tenants.show', $tenant))
            ->assertSessionHas('status');
    }

    public function test_cross_app_tenant_detail_is_not_found(): void
    {
        $tenant = $this->createTenant($this->otherApplication, TenantStatus::Active);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->get(route('admin.tenants.show', $tenant))
            ->assertNotFound();
    }

    public function test_dashboard_links_to_tenant_detail(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Active);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.tenants.show', $tenant, false), false);
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
