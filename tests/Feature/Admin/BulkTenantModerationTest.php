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

class BulkTenantModerationTest extends TestCase
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

    public function test_super_admin_can_bulk_approve_pending_tenants(): void
    {
        $first = $this->createTenant($this->activeApplication, TenantStatus::Pending, 'first');
        $second = $this->createTenant($this->activeApplication, TenantStatus::Pending, 'second');

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.bulk-update-status'), [
                'tenant_ids' => [$first->id, $second->id],
                'status' => TenantStatus::Active->value,
                'redirect' => ['status' => TenantStatus::Pending->value],
            ]);

        $response->assertRedirect(route('admin.dashboard', ['status' => TenantStatus::Pending->value]));
        $this->assertDatabaseHas('tenants', ['id' => $first->id, 'status' => TenantStatus::Active->value]);
        $this->assertDatabaseHas('tenants', ['id' => $second->id, 'status' => TenantStatus::Active->value]);
    }

    public function test_bulk_update_rejects_tenants_from_other_application(): void
    {
        $foreignTenant = $this->createTenant($this->otherApplication, TenantStatus::Pending, 'foreign');

        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.bulk-update-status'), [
                'tenant_ids' => [$foreignTenant->id],
                'status' => TenantStatus::Active->value,
            ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('tenants', [
            'id' => $foreignTenant->id,
            'status' => TenantStatus::Pending->value,
        ]);
    }

    public function test_bulk_update_requires_at_least_one_tenant(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->activeApplication->id])
            ->patch(route('admin.tenants.bulk-update-status'), [
                'tenant_ids' => [],
                'status' => TenantStatus::Active->value,
            ]);

        $response->assertSessionHasErrors('tenant_ids');
    }

    private function createTenant(
        Application $application,
        TenantStatus $status,
        string $slug,
    ): Tenant {
        return Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-'.$slug,
            'name' => ucfirst($slug).' Udruga',
            'slug' => $slug,
            'status' => $status,
            'plan' => 'basic',
        ]);
    }
}
