<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteCustomerWebhookRequest;
use App\Http\Requests\Api\DispatchCustomerEventRequest;
use App\Http\Requests\Api\ListCustomerWebhooksRequest;
use App\Http\Requests\Api\ManageCustomerWebhookRequest;
use App\Models\TenantCustomerWebhook;
use App\Services\Webhooks\CustomerWebhookService;
use Illuminate\Http\JsonResponse;

class PlatformCustomerWebhookController extends Controller
{
    public function __construct(
        private readonly CustomerWebhookService $customerWebhooks,
    ) {}

    public function index(ListCustomerWebhooksRequest $request): JsonResponse
    {
        $tenant = $this->customerWebhooks->resolveTenant(
            $request->string('application_slug')->toString(),
            $request->string('tenant_external_id')->toString(),
        );

        $webhooks = $this->customerWebhooks->listForTenant($tenant);

        return response()->json([
            'data' => $webhooks->map(fn (TenantCustomerWebhook $webhook): array => $this->serializeWebhook($webhook))->values(),
        ]);
    }

    public function store(ManageCustomerWebhookRequest $request): JsonResponse
    {
        $tenant = $this->customerWebhooks->resolveTenant(
            $request->string('application_slug')->toString(),
            $request->string('tenant_external_id')->toString(),
        );

        $webhook = $this->customerWebhooks->subscribe(
            $tenant,
            $request->string('url')->toString(),
            $request->input('events', []),
            $request->string('description')->toString() ?: null,
        );

        return response()->json([
            'data' => $this->serializeWebhook($webhook, includeSecret: true),
        ], 201);
    }

    public function destroy(DeleteCustomerWebhookRequest $request, int $webhookId): JsonResponse
    {
        $tenant = $this->customerWebhooks->resolveTenant(
            $request->string('application_slug')->toString(),
            $request->string('tenant_external_id')->toString(),
        );

        if (! $this->customerWebhooks->unsubscribe($tenant, $webhookId)) {
            abort(404);
        }

        return response()->json([
            'message' => 'Webhook uklonjen.',
        ]);
    }

    public function dispatch(DispatchCustomerEventRequest $request): JsonResponse
    {
        $tenant = $this->customerWebhooks->resolveTenant(
            $request->string('application_slug')->toString(),
            $request->string('tenant_external_id')->toString(),
        );

        $deliveries = $this->customerWebhooks->dispatch(
            $tenant,
            $request->string('event')->toString(),
            $request->input('data', []),
        );

        return response()->json([
            'data' => [
                'deliveries' => $deliveries,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWebhook(TenantCustomerWebhook $webhook, bool $includeSecret = false): array
    {
        $data = [
            'id' => $webhook->id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'description' => $webhook->description,
            'is_active' => $webhook->is_active,
            'last_delivered_at' => $webhook->last_delivered_at?->toIso8601String(),
            'last_failed_at' => $webhook->last_failed_at?->toIso8601String(),
            'failure_count' => $webhook->failure_count,
            'created_at' => $webhook->created_at?->toIso8601String(),
        ];

        if ($includeSecret) {
            $data['secret'] = $webhook->secret;
        }

        return $data;
    }
}
