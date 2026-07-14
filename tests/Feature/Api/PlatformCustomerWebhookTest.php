<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\TenantCustomerWebhook;
use App\Support\CustomerWebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformCustomerWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhook.secret' => 'test-webhook-secret']);
    }

    public function test_rejects_unauthenticated_customer_webhook_requests(): void
    {
        $this->getJson('/api/platform/customer-webhooks')
            ->assertUnauthorized();
    }

    public function test_tenant_can_subscribe_and_list_customer_webhooks(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'name' => 'Test udruga',
            'slug' => 'test-udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/customer-webhooks', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '42',
                'url' => 'https://hooks.example.test/udruga',
                'events' => ['member.approved', 'invoice.paid'],
                'description' => 'Zapier',
            ])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://hooks.example.test/udruga')
            ->assertJsonPath('data.events', ['member.approved', 'invoice.paid'])
            ->assertJsonStructure(['data' => ['id', 'secret']]);

        $this->withToken('test-webhook-secret')
            ->getJson('/api/platform/customer-webhooks?application_slug=udruga-saas&tenant_external_id=42')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.secret');

        $this->assertDatabaseHas('tenant_customer_webhooks', [
            'tenant_id' => $tenant->id,
            'description' => 'Zapier',
        ]);
    }

    public function test_tenant_can_delete_customer_webhook(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '7',
            'name' => 'Test udruga',
            'slug' => 'test-udruga-7',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $webhook = TenantCustomerWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://hooks.example.test/remove',
            'secret' => 'whsec_test_secret_value',
            'events' => ['member.approved'],
            'is_active' => true,
        ]);

        $this->withToken('test-webhook-secret')
            ->deleteJson('/api/platform/customer-webhooks/'.$webhook->id.'?application_slug=udruga-saas&tenant_external_id=7')
            ->assertOk();

        $this->assertDatabaseMissing('tenant_customer_webhooks', [
            'id' => $webhook->id,
        ]);
    }

    public function test_dispatch_delivers_signed_payload_to_subscribed_url(): void
    {
        Http::fake([
            'https://hooks.example.test/events' => Http::response(['ok' => true], 200),
        ]);

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '99',
            'name' => 'Webhook udruga',
            'slug' => 'webhook-udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $subscribe = $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/customer-webhooks', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '99',
                'url' => 'https://hooks.example.test/events',
                'events' => ['member.approved'],
            ])
            ->assertCreated();

        $secret = (string) $subscribe->json('data.secret');

        $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/events/dispatch', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '99',
                'event' => 'member.approved',
                'data' => [
                    'member_id' => 15,
                    'email' => 'clan@udruga.hr',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.deliveries', 1);

        Http::assertSent(function ($request) use ($secret): bool {
            if ($request->url() !== 'https://hooks.example.test/events') {
                return false;
            }

            $body = $request->body();
            $timestamp = (int) $request->header('X-Webhook-Timestamp')[0];
            $signature = (string) $request->header('X-Webhook-Signature')[0];
            $expected = 'sha256='.CustomerWebhookSignature::sign($secret, $timestamp, $body);

            return $signature === $expected
                && $request->header('X-Webhook-Event')[0] === 'member.approved'
                && str_contains($body, '"member_id":15');
        });

        $webhook = TenantCustomerWebhook::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($webhook?->last_delivered_at);
    }

    public function test_dispatch_skips_webhooks_not_subscribed_to_event(): void
    {
        Http::fake();

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '55',
            'name' => 'Skip udruga',
            'slug' => 'skip-udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/customer-webhooks', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '55',
                'url' => 'https://hooks.example.test/skip',
                'events' => ['invoice.paid'],
            ])
            ->assertCreated();

        $this->withToken('test-webhook-secret')
            ->postJson('/api/platform/events/dispatch', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '55',
                'event' => 'member.approved',
                'data' => ['member_id' => 1],
            ])
            ->assertOk()
            ->assertJsonPath('data.deliveries', 0);

        Http::assertNothingSent();
    }
}
