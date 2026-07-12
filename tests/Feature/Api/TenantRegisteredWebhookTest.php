<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class TenantRegisteredWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhook.secret' => 'test-webhook-secret']);
    }

    public function test_rejects_unauthenticated_webhook(): void
    {
        $response = $this->postJson('/api/webhooks/tenants/registered', []);

        $response->assertUnauthorized();
    }

    public function test_creates_tenant_from_webhook_payload(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $response = $this->withToken('test-webhook-secret')
            ->postJson('/api/webhooks/tenants/registered', [
                'application_slug' => 'udruga-saas',
                'organization' => [
                    'id' => 99,
                    'name' => 'Nova udruga',
                    'slug' => 'nova-udruga',
                    'status' => 'pending',
                    'plan' => 'basic',
                    'email' => 'nova@udruga.hr',
                ],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tenants', [
            'external_id' => '99',
            'name' => 'Nova udruga',
            'slug' => 'nova-udruga',
            'status' => 'pending',
            'plan' => 'basic',
        ]);
    }

    public function test_updates_existing_tenant_on_duplicate_webhook(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '5',
            'name' => 'Staro ime',
            'slug' => 'staro-ime',
            'status' => 'pending',
            'plan' => 'basic',
        ]);

        $response = $this->withToken('test-webhook-secret')
            ->postJson('/api/webhooks/tenants/registered', [
                'application_slug' => 'udruga-saas',
                'organization' => [
                    'id' => 5,
                    'name' => 'Novo ime',
                    'slug' => 'novo-ime',
                    'status' => 'pending',
                    'plan' => 'basic',
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tenants', [
            'external_id' => '5',
            'name' => 'Novo ime',
            'slug' => 'novo-ime',
        ]);
    }
}
