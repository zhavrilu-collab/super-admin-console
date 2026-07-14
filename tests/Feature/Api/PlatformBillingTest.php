<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Billing\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhook.secret' => 'test-webhook-secret']);

        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            'sk_test_example',
        );
    }

    public function test_rejects_unauthenticated_billing_checkout(): void
    {
        $this->postJson('/api/platform/billing/checkout', [])
            ->assertUnauthorized();
    }

    public function test_checkout_returns_stripe_session_url(): void
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
            ->update(['stripe_price_id' => 'price_standard_test']);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'name' => 'Test udruga',
            'slug' => 'test-udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('isConfigured')->andReturn(true);
        $billing->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'url' => 'https://checkout.stripe.com/test-session',
                'session_id' => 'cs_test_123',
            ]);

        $this->app->instance(StripeBillingService::class, $billing);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/billing/checkout', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '42',
                'plan_slug' => 'standard',
                'success_url' => 'https://app.test/billing/success',
                'cancel_url' => 'https://app.test/billing/cancel',
                'customer_email' => 'owner@udruga.hr',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'checkout')
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/test-session')
            ->assertJsonPath('data.session_id', 'cs_test_123');
    }

    public function test_portal_returns_stripe_portal_url(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'name' => 'Test udruga',
            'slug' => 'test-udruga',
            'status' => 'active',
            'plan' => 'basic',
            'stripe_customer_id' => 'cus_test_123',
        ]);

        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('isConfigured')->andReturn(true);
        $billing->shouldReceive('createPortalSession')
            ->once()
            ->andReturn(['url' => 'https://billing.stripe.com/portal/test']);

        $this->app->instance(StripeBillingService::class, $billing);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/billing/portal', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '42',
                'return_url' => 'https://app.test/postavke/pretplata',
            ])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://billing.stripe.com/portal/test');
    }
}
