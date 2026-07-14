<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Billing\BillingMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class BillingMetricsServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_calculates_mrr_from_active_subscriptions(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'standard')
            ->update(['monthly_price_cents' => 2900]);

        SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'premium')
            ->update(['monthly_price_cents' => 7900]);

        $tenantStandard = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '1',
            'name' => 'Standard org',
            'slug' => 'standard-org',
            'status' => 'active',
            'plan' => 'standard',
        ]);

        $tenantPremium = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '2',
            'name' => 'Premium org',
            'slug' => 'premium-org',
            'status' => 'active',
            'plan' => 'premium',
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenantStandard->id,
            'stripe_subscription_id' => 'sub_std',
            'stripe_price_id' => 'price_std',
            'status' => SubscriptionStatus::Active,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenantPremium->id,
            'stripe_subscription_id' => 'sub_prem',
            'stripe_price_id' => 'price_prem',
            'status' => SubscriptionStatus::Active,
        ]);

        $metrics = app(BillingMetricsService::class)->metricsForApplication($application->id);

        $this->assertSame(10800, $metrics['mrr_cents']);
        $this->assertSame(129600, $metrics['arr_cents']);
        $this->assertSame(2, $metrics['active_subscriptions']);
        $this->assertSame(129600, $metrics['estimated_ltv_cents']);
    }
}
