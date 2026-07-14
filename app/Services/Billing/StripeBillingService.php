<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Admin\ConsoleSettingsService;
use Carbon\Carbon;
use Stripe\StripeClient;

class StripeBillingService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ConsoleSettingsService $consoleSettings,
    ) {}

    public function isConfigured(): bool
    {
        return $this->secretKey() !== null && $this->secretKey() !== '';
    }

    public function secretKey(): ?string
    {
        return $this->consoleSettings->get(
            ConsoleSettingsService::STRIPE_SECRET_KEY,
            config('billing.stripe_secret_key'),
        );
    }

    public function publishableKey(): ?string
    {
        return $this->consoleSettings->get(
            ConsoleSettingsService::STRIPE_PUBLISHABLE_KEY,
            config('billing.stripe_publishable_key'),
        );
    }

    public function webhookSecret(): ?string
    {
        return $this->consoleSettings->get(
            ConsoleSettingsService::STRIPE_WEBHOOK_SECRET,
            config('billing.stripe_webhook_secret'),
        );
    }

    public function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient($this->secretKey());
        }

        return $this->client;
    }

    public function ensureStripeCustomer(Tenant $tenant, ?string $email = null): string
    {
        if (is_string($tenant->stripe_customer_id) && $tenant->stripe_customer_id !== '') {
            return $tenant->stripe_customer_id;
        }

        $payload = array_filter([
            'email' => $email,
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'external_id' => (string) $tenant->external_id,
                'application_id' => (string) $tenant->application_id,
            ],
        ], fn ($value) => $value !== null && $value !== '');

        $customer = $this->client()->customers->create($payload);

        $tenant->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * @return array{url: string|null, session_id: string}
     */
    public function createCheckoutSession(
        Tenant $tenant,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
        ?string $customerEmail = null,
    ): array {
        if ($plan->stripe_price_id === null || $plan->stripe_price_id === '') {
            throw new \InvalidArgumentException('Paket nema mapiran Stripe Price ID.');
        }

        $customerId = $this->ensureStripeCustomer($tenant, $customerEmail);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_slug' => $plan->slug,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_slug' => $plan->slug,
                ],
            ],
        ]);

        return [
            'url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    /**
     * @return array{url: string}
     */
    public function createPortalSession(Tenant $tenant, string $returnUrl): array
    {
        if ($tenant->stripe_customer_id === null || $tenant->stripe_customer_id === '') {
            throw new \InvalidArgumentException('Tenant nema Stripe customer ID.');
        }

        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $tenant->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return [
            'url' => $session->url,
        ];
    }

    public function changeSubscriptionPlan(Tenant $tenant, SubscriptionPlan $plan): TenantSubscription
    {
        if ($plan->stripe_price_id === null || $plan->stripe_price_id === '') {
            throw new \InvalidArgumentException('Paket nema mapiran Stripe Price ID.');
        }

        $tenant->loadMissing('activeSubscription');
        $active = $tenant->activeSubscription;

        if ($active === null || ! $active->isActive()) {
            throw new \InvalidArgumentException('Tenant nema aktivnu Stripe pretplatu za promjenu paketa.');
        }

        $subscription = $this->client()->subscriptions->retrieve($active->stripe_subscription_id);
        $items = $subscription->items->data ?? [];

        if ($items === []) {
            throw new \RuntimeException('Stripe pretplata nema stavki za ažuriranje.');
        }

        $itemId = $items[0]->id;

        $updated = $this->client()->subscriptions->update($active->stripe_subscription_id, [
            'items' => [
                [
                    'id' => $itemId,
                    'price' => $plan->stripe_price_id,
                ],
            ],
            'proration_behavior' => (string) config('billing.proration_behavior', 'create_prorations'),
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        return $this->syncSubscriptionFromStripePayload($tenant, $updated->toArray());
    }

    public function findPlanByStripePriceId(string $priceId, ?int $applicationId = null): ?SubscriptionPlan
    {
        $query = SubscriptionPlan::query()->where('stripe_price_id', $priceId);

        if ($applicationId !== null) {
            $query->where('application_id', $applicationId);
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    public function syncSubscriptionFromStripePayload(Tenant $tenant, array $stripeSubscription): TenantSubscription
    {
        $priceId = $this->extractPriceId($stripeSubscription);
        $status = SubscriptionStatus::tryFrom((string) ($stripeSubscription['status'] ?? ''))
            ?? SubscriptionStatus::Incomplete;

        $subscription = TenantSubscription::query()->updateOrCreate(
            ['stripe_subscription_id' => (string) $stripeSubscription['id']],
            [
                'tenant_id' => $tenant->id,
                'stripe_price_id' => $priceId,
                'status' => $status,
                'current_period_start' => $this->timestampToCarbon($stripeSubscription['current_period_start'] ?? null),
                'current_period_end' => $this->timestampToCarbon($stripeSubscription['current_period_end'] ?? null),
                'cancel_at_period_end' => (bool) ($stripeSubscription['cancel_at_period_end'] ?? false),
                'canceled_at' => $this->timestampToCarbon($stripeSubscription['canceled_at'] ?? null),
            ],
        );

        if ($priceId !== null) {
            $plan = $this->findPlanByStripePriceId($priceId, $tenant->application_id);

            if ($plan !== null) {
                $tenant->forceFill(['plan' => $plan->slug])->save();
            }
        }

        if (isset($stripeSubscription['customer']) && is_string($stripeSubscription['customer'])) {
            $tenant->forceFill(['stripe_customer_id' => $stripeSubscription['customer']])->save();
        }

        return $subscription->fresh(['tenant']);
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    private function extractPriceId(array $stripeSubscription): ?string
    {
        $items = $stripeSubscription['items']['data'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        $price = $items[0]['price']['id'] ?? null;

        return is_string($price) && $price !== '' ? $price : null;
    }

    private function timestampToCarbon(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }
}
