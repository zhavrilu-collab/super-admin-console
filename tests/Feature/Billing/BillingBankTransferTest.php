<?php

namespace Tests\Feature\Billing;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingBankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webhook.secret' => 'test-webhook-secret',
            'billing.currency' => 'eur',
        ]);
    }

    public function test_bank_transfer_api_returns_payment_instructions(): void
    {
        config([
            'billing.bank_transfer.enabled' => true,
            'billing.bank_transfer.iban' => 'HR1234567890123456789',
            'billing.bank_transfer.recipient_name' => 'Udruga SaaS d.o.o.',
            'billing.bank_transfer.payment_days' => 14,
        ]);
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        SubscriptionPlan::query()->create([
            'application_id' => $application->id,
            'name' => 'Standardni',
            'slug' => 'standard',
            'badge_class' => 'primary',
            'monthly_price_cents' => 1900,
        ]);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '42',
            'name' => 'Test Udruga',
            'slug' => 'test-udruga',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $response = $this->withToken('test-webhook-secret')
            ->postJson('/api/v1/platform/billing/bank-transfer', [
                'application_slug' => 'udruga-saas',
                'tenant_external_id' => '42',
                'plan_slug' => 'standard',
                'customer_email' => 'udruga@test.hr',
                'payer_oib' => '53048407602',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.recipient_name', 'Udruga SaaS d.o.o.')
            ->assertJsonPath('data.iban', 'HR1234567890123456789')
            ->assertJsonPath('data.plan_slug', 'standard')
            ->assertJsonPath('data.payment_days', 14);

        $reference = (string) $response->json('data.reference');
        $this->assertStringStartsWith('53048407602-STD-', $reference);
        $this->assertSame(22, strlen($reference));
        $this->assertStringContainsString('53048407602-STD-', (string) $response->json('data.reference_display'));
        $this->assertNotEmpty($response->json('data.purpose'));
    }
}
