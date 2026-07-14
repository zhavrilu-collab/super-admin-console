<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            'sk_test_example',
        );

        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::STRIPE_WEBHOOK_SECRET,
            $this->webhookSecret,
        );
    }

    public function test_rejects_invalid_stripe_signature(): void
    {
        $this->postJson('/api/webhooks/stripe', ['type' => 'test'], [
            'Stripe-Signature' => 'invalid',
        ])->assertStatus(400);
    }

    public function test_subscription_updated_webhook_syncs_tenant_plan(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'premium')
            ->update(['stripe_price_id' => 'price_premium_webhook']);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '99',
            'name' => 'Webhook udruga',
            'slug' => 'webhook-udruga',
            'status' => 'active',
            'plan' => 'basic',
            'stripe_customer_id' => 'cus_webhook_123',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_sub_updated',
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_webhook_456',
                    'object' => 'subscription',
                    'status' => 'active',
                    'customer' => 'cus_webhook_123',
                    'current_period_start' => 1700000000,
                    'current_period_end' => 1702678400,
                    'cancel_at_period_end' => false,
                    'metadata' => [
                        'tenant_id' => (string) $tenant->id,
                    ],
                    'items' => [
                        'data' => [
                            ['price' => ['id' => 'price_premium_webhook']],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->signStripePayload($payload, $this->webhookSecret);

        $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload,
        )->assertOk()->assertJson(['received' => true]);

        $tenant->refresh();

        $this->assertSame('premium', $tenant->plan);
        $this->assertDatabaseHas('tenant_subscriptions', [
            'tenant_id' => $tenant->id,
            'stripe_subscription_id' => 'sub_webhook_456',
            'status' => 'active',
            'stripe_price_id' => 'price_premium_webhook',
        ]);
    }

    private function signStripePayload(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
