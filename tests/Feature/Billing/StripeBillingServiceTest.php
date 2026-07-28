<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Billing\StripeBillingService;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_configured_when_secret_key_is_set(): void
    {
        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            'sk_test_example',
        );

        $service = app(StripeBillingService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('sk_test_example', $service->secretKey());
    }

    public function test_find_plan_by_stripe_price_id(): void
    {
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);

        SubscriptionPlan::query()->create([
            'application_id' => $application->id,
            'name' => 'Standardni',
            'slug' => 'standard',
            'badge_class' => 'primary',
            'stripe_price_id' => 'price_standard_123',
        ]);

        $service = app(StripeBillingService::class);

        $plan = $service->findPlanByStripePriceId('price_standard_123', $application->id);

        $this->assertNotNull($plan);
        $this->assertSame('standard', $plan->slug);
    }

    public function test_sync_subscription_from_stripe_payload_updates_tenant_plan(): void
    {
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);

        SubscriptionPlan::query()->create([
            'application_id' => $application->id,
            'name' => 'Napredni',
            'slug' => 'premium',
            'badge_class' => 'dark',
            'stripe_price_id' => 'price_premium_456',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-1',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $service = app(StripeBillingService::class);

        $subscription = $service->syncSubscriptionFromStripePayload($tenant, [
            'id' => 'sub_abc123',
            'status' => 'active',
            'customer' => 'cus_xyz789',
            'current_period_start' => 1700000000,
            'current_period_end' => 1702678400,
            'cancel_at_period_end' => false,
            'items' => [
                'data' => [
                    ['price' => ['id' => 'price_premium_456']],
                ],
            ],
        ]);

        $this->assertSame('sub_abc123', $subscription->stripe_subscription_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('price_premium_456', $subscription->stripe_price_id);

        $tenant->refresh();
        $this->assertSame('premium', $tenant->plan);
        $this->assertSame('cus_xyz789', $tenant->stripe_customer_id);

        $this->assertDatabaseHas('tenant_subscriptions', [
            'tenant_id' => $tenant->id,
            'stripe_subscription_id' => 'sub_abc123',
            'status' => 'active',
        ]);
    }

    public function test_ensure_stripe_catalog_skips_free_plans(): void
    {
        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            'sk_test_example',
        );

        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'application_id' => $application->id,
            'name' => 'Osnovni',
            'slug' => 'basic',
            'badge_class' => 'secondary',
            'monthly_price_cents' => 0,
        ]);

        $result = app(StripeBillingService::class)->ensureStripeCatalog($plan);

        $this->assertNull($result->stripe_price_id);
        $this->assertNull($result->stripe_product_id);
    }

    public function test_plan_store_with_sync_calls_ensure_stripe_catalog(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);

        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            'sk_test_example',
        );

        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('isConfigured')->andReturn(true);
        $billing->shouldReceive('ensureStripeCatalog')
            ->once()
            ->andReturnUsing(function (SubscriptionPlan $plan) {
                $plan->forceFill([
                    'stripe_product_id' => 'prod_auto_1',
                    'stripe_price_id' => 'price_auto_1',
                ])->save();

                return $plan->fresh();
            });
        $this->app->instance(StripeBillingService::class, $billing);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.subscription-plans.store'), [
                'name' => 'Pro',
                'slug' => 'pro',
                'badge_class' => 'info',
                'monthly_price' => '49.00',
                'sync_stripe_catalog' => '1',
            ])
            ->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'pro',
            'stripe_product_id' => 'prod_auto_1',
            'stripe_price_id' => 'price_auto_1',
            'monthly_price_cents' => 4900,
        ]);
    }
}
