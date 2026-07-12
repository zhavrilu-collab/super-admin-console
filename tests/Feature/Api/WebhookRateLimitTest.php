<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhook.secret' => 'test-webhook-secret']);
    }

    public function test_webhook_endpoint_is_rate_limited(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        RateLimiter::clear('webhooks');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withToken('test-webhook-secret')
                ->postJson('/api/webhooks/tenants/registered', [
                    'application_slug' => 'udruga-saas',
                    'organization' => [
                        'id' => 1000 + $attempt,
                        'name' => 'Tenant '.$attempt,
                        'slug' => 'tenant-'.$attempt,
                        'status' => 'pending',
                        'plan' => 'basic',
                    ],
                ])
                ->assertCreated();
        }

        $response = $this->withToken('test-webhook-secret')
            ->postJson('/api/webhooks/tenants/registered', [
                'application_slug' => 'udruga-saas',
                'organization' => [
                    'id' => 9999,
                    'name' => 'Previše zahtjeva',
                    'slug' => 'previse-zahtjeva',
                    'status' => 'pending',
                    'plan' => 'basic',
                ],
            ]);

        $response->assertStatus(429);
    }
}
