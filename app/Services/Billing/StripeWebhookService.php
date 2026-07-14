<?php

namespace App\Services\Billing;

use App\Enums\DunningResolution;
use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Services\Admin\TenantSyncService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookService
{
    public function __construct(
        private readonly StripeBillingService $billing,
        private readonly TenantSyncService $tenantSync,
        private readonly BillingDunningService $dunning,
    ) {}

    /**
     * @throws UnexpectedValueException
     * @throws SignatureVerificationException
     */
    public function constructEvent(string $payload, string $signatureHeader): Event
    {
        $secret = $this->billing->webhookSecret();

        if ($secret === null || $secret === '') {
            throw new InvalidArgumentException('Stripe webhook secret nije konfiguriran.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $secret);
    }

    public function handle(Event $event): void
    {
        match ($event->type) {
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionEvent($event),
            'invoice.paid' => $this->handleInvoicePaid($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            default => null,
        };
    }

    private function handleSubscriptionEvent(Event $event): void
    {
        /** @var array<string, mixed> $subscription */
        $subscription = $event->data->object->toArray();

        $tenant = $this->resolveTenantFromSubscription($subscription);

        if ($tenant === null) {
            Log::warning('Stripe subscription event without resolvable tenant.', [
                'event_id' => $event->id,
                'subscription_id' => $subscription['id'] ?? null,
            ]);

            return;
        }

        $previousPlan = $tenant->plan;

        $this->billing->syncSubscriptionFromStripePayload($tenant, $subscription);

        $tenant->refresh();

        if ($subscription['status'] === SubscriptionStatus::Canceled->value) {
            $this->dunning->resolveForTenant($tenant, DunningResolution::Canceled);
        }

        if ($tenant->plan !== $previousPlan) {
            try {
                $this->tenantSync->pushPlan($tenant, $tenant->plan);
            } catch (\Throwable $exception) {
                Log::warning('Stripe subscription plan sync to SaaS failed.', [
                    'tenant_id' => $tenant->id,
                    'plan' => $tenant->plan,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function handleInvoicePaid(Event $event): void
    {
        /** @var array<string, mixed> $invoice */
        $invoice = $event->data->object->toArray();
        $subscriptionId = $invoice['subscription'] ?? null;

        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return;
        }

        $tenant = $this->resolveTenantFromCustomerId($invoice['customer'] ?? null);

        if ($tenant === null) {
            return;
        }

        try {
            $subscription = $this->billing->client()->subscriptions->retrieve($subscriptionId)->toArray();
            $this->billing->syncSubscriptionFromStripePayload($tenant, $subscription);
        } catch (\Throwable $exception) {
            Log::warning('Stripe invoice.paid sync failed.', [
                'event_id' => $event->id,
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }

        $this->dunning->resolveForTenant($tenant, DunningResolution::Paid);
    }

    private function handleInvoicePaymentFailed(Event $event): void
    {
        /** @var array<string, mixed> $invoice */
        $invoice = $event->data->object->toArray();
        $subscriptionId = $invoice['subscription'] ?? null;

        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return;
        }

        $tenant = $this->resolveTenantFromCustomerId($invoice['customer'] ?? null);

        if ($tenant === null) {
            return;
        }

        $synced = null;

        try {
            $subscription = $this->billing->client()->subscriptions->retrieve($subscriptionId)->toArray();
            $synced = $this->billing->syncSubscriptionFromStripePayload($tenant, $subscription);
        } catch (\Throwable $exception) {
            Log::warning('Stripe invoice.payment_failed sync failed.', [
                'event_id' => $event->id,
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($synced === null || $this->dunning->shouldOpenCaseForSubscriptionStatus($synced->status)) {
            $this->dunning->recordPaymentFailure($tenant, $synced, $invoice);
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function resolveTenantFromSubscription(array $subscription): ?Tenant
    {
        $metadata = $subscription['metadata'] ?? [];

        if (is_array($metadata) && isset($metadata['tenant_id'])) {
            $tenant = Tenant::query()->find($metadata['tenant_id']);

            if ($tenant !== null) {
                return $tenant;
            }
        }

        return $this->resolveTenantFromCustomerId($subscription['customer'] ?? null);
    }

    private function resolveTenantFromCustomerId(mixed $customerId): ?Tenant
    {
        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        return Tenant::query()
            ->where('stripe_customer_id', $customerId)
            ->first();
    }
}
