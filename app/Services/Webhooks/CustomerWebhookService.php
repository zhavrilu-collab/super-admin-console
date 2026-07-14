<?php

namespace App\Services\Webhooks;

use App\Enums\CustomerWebhookEvent;
use App\Jobs\DeliverCustomerWebhookJob;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\TenantCustomerWebhook;
use Illuminate\Support\Str;

class CustomerWebhookService
{
    /**
     * @param  list<string>  $events
     */
    public function subscribe(
        Tenant $tenant,
        string $url,
        array $events,
        ?string $description = null,
    ): TenantCustomerWebhook {
        $normalizedEvents = $this->normalizeEvents($events);

        return TenantCustomerWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'url' => $url,
            'secret' => Str::random(48),
            'events' => $normalizedEvents,
            'description' => $description,
            'is_active' => true,
        ]);
    }

    public function unsubscribe(Tenant $tenant, int $webhookId): bool
    {
        return TenantCustomerWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($webhookId)
            ->delete() > 0;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TenantCustomerWebhook>
     */
    public function listForTenant(Tenant $tenant)
    {
        return TenantCustomerWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->get();
    }

    public function resolveTenant(string $applicationSlug, string $tenantExternalId): Tenant
    {
        $application = Application::query()
            ->where('slug', $applicationSlug)
            ->firstOrFail();

        return Tenant::query()
            ->where('application_id', $application->id)
            ->where('external_id', $tenantExternalId)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Tenant $tenant, CustomerWebhookEvent|string $event, array $payload): int
    {
        $eventName = $event instanceof CustomerWebhookEvent ? $event->value : $event;

        $webhooks = TenantCustomerWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get()
            ->filter(static fn (TenantCustomerWebhook $webhook): bool => $webhook->subscribesTo($eventName));

        foreach ($webhooks as $webhook) {
            DeliverCustomerWebhookJob::dispatch(
                $webhook,
                $eventName,
                $payload,
            );
        }

        return $webhooks->count();
    }

    /**
     * @param  list<string>  $events
     * @return list<string>
     */
    private function normalizeEvents(array $events): array
    {
        return array_values(array_unique(array_map(
            static fn (string $event): string => trim($event),
            $events,
        )));
    }
}
