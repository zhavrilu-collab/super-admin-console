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

class TenantModerationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    private User $superAdmin;

    private Application $activeApplication;

    private Application $otherApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
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

    public function test_super_admin_can_approve_tenant(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Pending);

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-status', $tenant), [
                'status' => TenantStatus::Active->value,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => TenantStatus::Active->value,
        ]);
    }

    public function test_super_admin_can_change_tenant_plan(): void
    {
        $tenant = $this->createTenant($this->activeApplication, TenantStatus::Active, 'basic');

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-plan', $tenant), [
                'plan' => 'premium',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan' => 'premium',
        ]);
    }

    public function test_cross_app_tenant_update_is_rejected(): void
    {
        $tenant = $this->createTenant($this->otherApplication, TenantStatus::Pending);

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.update-status', $tenant), [
                'status' => TenantStatus::Active->value,
            ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => TenantStatus::Pending->value,
        ]);
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
