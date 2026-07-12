<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class AdminDashboardFiltersTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    private User $superAdmin;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($this->application);
    }

    public function test_dashboard_filters_tenants_by_search_query(): void
    {
        $this->createTenant('Alpha Udruga', 'alpha-udruga', TenantStatus::Active);
        $this->createTenant('Beta Savez', 'beta-savez', TenantStatus::Active);

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->get(route('admin.dashboard', ['q' => 'Alpha']));

        $response->assertOk();
        $response->assertSee('Alpha Udruga');
        $response->assertDontSee('Beta Savez');
    }

    public function test_dashboard_filters_tenants_by_status(): void
    {
        $this->createTenant('Pending Udruga', 'pending-udruga', TenantStatus::Pending);
        $this->createTenant('Active Udruga', 'active-udruga', TenantStatus::Active);

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->get(route('admin.dashboard', ['status' => TenantStatus::Pending->value]));

        $response->assertOk();
        $response->assertSee('Pending Udruga');
        $response->assertDontSee('Active Udruga');
    }

    public function test_dashboard_paginates_tenant_list(): void
    {
        for ($index = 1; $index <= 30; $index++) {
            $this->createTenant('Tenant '.$index, 'tenant-'.$index, TenantStatus::Active);
        }

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->get(route('admin.dashboard', ['per_page' => 10]));

        $response->assertOk();
        $response->assertSee('Prikazano 1–10 od 30');
        $response->assertSee('Tenant 1');
        $response->assertDontSee('Tenant 30');
    }

    public function test_dashboard_shows_empty_state_for_filters_without_results(): void
    {
        $this->createTenant('Jedina Udruga', 'jedina-udruga', TenantStatus::Active);

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->get(route('admin.dashboard', ['q' => 'nema-rezultata']));

        $response->assertOk();
        $response->assertSee('Nema tenanata koji odgovaraju filterima.');
    }

    private function createTenant(string $name, string $slug, TenantStatus $status): Tenant
    {
        return Tenant::query()->create([
            'application_id' => $this->application->id,
            'external_id' => 'ext-'.$slug,
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'plan' => 'basic',
        ]);
    }
}
