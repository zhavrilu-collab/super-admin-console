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

class SubscriptionPlanCrudTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_super_admin_can_view_plans_index(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Paketi pretplate')
            ->assertSee('Osnovni')
            ->assertSee('Standardni')
            ->assertSee('Napredni');
    }

    public function test_super_admin_can_create_plan(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.subscription-plans.store'), [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'member_limit' => 1000,
                'badge_class' => 'success',
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'member_limit' => 1000,
        ]);
    }

    public function test_super_admin_can_create_plan_with_stripe_ids(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.subscription-plans.store'), [
                'name' => 'Pro',
                'slug' => 'pro',
                'badge_class' => 'info',
                'stripe_product_id' => 'prod_test_1',
                'stripe_price_id' => 'price_test_1',
            ])
            ->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'pro',
            'stripe_product_id' => 'prod_test_1',
            'stripe_price_id' => 'price_test_1',
        ]);
    }

    public function test_cannot_delete_plan_with_tenants(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $plan = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'basic')
            ->firstOrFail();

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-1',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->delete(route('admin.subscription-plans.destroy', $plan))
            ->assertRedirect(route('admin.subscription-plans.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
    }
}
