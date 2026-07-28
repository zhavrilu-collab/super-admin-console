<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_super_admin_can_view_stats_index(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-1',
            'name' => 'Alpha Org',
            'slug' => 'alpha-org',
            'status' => 'active',
            'plan' => 'standard',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.stats.index'))
            ->assertOk()
            ->assertSee('Statistika')
            ->assertSee('Tenanti po planu')
            ->assertSee('Feature coverage');
    }

    public function test_drill_down_lists_tenants_for_plan_metric(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-std',
            'name' => 'Standard Tenant',
            'slug' => 'standard-tenant',
            'status' => 'active',
            'plan' => 'standard',
        ]);
        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-basic',
            'name' => 'Basic Tenant',
            'slug' => 'basic-tenant',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.stats.index', ['metric' => 'plan', 'value' => 'standard']))
            ->assertOk()
            ->assertSee('Standard Tenant')
            ->assertDontSee('Basic Tenant');
    }

    public function test_feature_drill_down_includes_tenants_on_plans_with_feature(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-premium',
            'name' => 'Premium Tenant',
            'slug' => 'premium-tenant',
            'status' => 'active',
            'plan' => 'premium',
        ]);
        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-basic-2',
            'name' => 'Basic Only',
            'slug' => 'basic-only',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->assertTrue(
            SubscriptionPlan::query()
                ->where('application_id', $application->id)
                ->where('slug', 'premium')
                ->firstOrFail()
                ->boolFeature('custom_domain'),
        );

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.stats.index', ['metric' => 'feature', 'value' => 'custom_domain']))
            ->assertOk()
            ->assertSee('Premium Tenant')
            ->assertDontSee('Basic Only');
    }
}
