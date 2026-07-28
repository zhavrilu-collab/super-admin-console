<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Billing\StripeBillingService;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class AdminTenantBillingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    private User $superAdmin;

    private Application $application;

    private Tenant $tenant;

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

        SubscriptionPlan::query()
            ->where('application_id', $this->application->id)
            ->where('slug', 'standard')
            ->update([
                'stripe_price_id' => 'price_standard_test',
                'monthly_price_cents' => 2900,
            ]);

        $this->tenant = Tenant::query()->create([
            'application_id' => $this->application->id,
            'external_id' => '42',
            'name' => 'Billing Tenant',
            'slug' => 'billing-tenant',
            'status' => 'active',
            'plan' => 'basic',
            'stripe_customer_id' => 'cus_test_admin',
        ]);

        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            'sk_test_example',
        );
    }

    public function test_checkout_redirects_to_stripe_url(): void
    {
        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('isConfigured')->andReturn(true);
        $billing->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'url' => 'https://checkout.stripe.com/admin-test',
                'session_id' => 'cs_admin_1',
            ]);
        $this->app->instance(StripeBillingService::class, $billing);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->post(route('admin.tenants.billing.checkout', $this->tenant), [
                'plan_slug' => 'standard',
                'customer_email' => 'owner@example.com',
            ])
            ->assertRedirect('https://checkout.stripe.com/admin-test');
    }

    public function test_portal_redirects_to_stripe_portal(): void
    {
        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('isConfigured')->andReturn(true);
        $billing->shouldReceive('createPortalSession')
            ->once()
            ->andReturn(['url' => 'https://billing.stripe.com/portal/admin']);
        $this->app->instance(StripeBillingService::class, $billing);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->post(route('admin.tenants.billing.portal', $this->tenant))
            ->assertRedirect('https://billing.stripe.com/portal/admin');
    }

    public function test_change_billing_plan_uses_stripe_proration(): void
    {
        TenantSubscription::query()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_subscription_id' => 'sub_admin_1',
            'stripe_price_id' => 'price_standard_test',
            'status' => 'active',
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);

        SubscriptionPlan::query()
            ->where('application_id', $this->application->id)
            ->where('slug', 'premium')
            ->update(['stripe_price_id' => 'price_premium_test']);

        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('isConfigured')->andReturn(true);
        $billing->shouldReceive('changeSubscriptionPlan')
            ->once()
            ->andReturnUsing(function (Tenant $tenant) {
                $tenant->forceFill(['plan' => 'premium'])->save();

                return $tenant->activeSubscription()->first();
            });
        $this->app->instance(StripeBillingService::class, $billing);

        $this->actingAs($this->superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $this->application->id])
            ->post(route('admin.tenants.billing.change-plan', $this->tenant), [
                'plan_slug' => 'premium',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('premium', $this->tenant->fresh()->plan);
    }
}
