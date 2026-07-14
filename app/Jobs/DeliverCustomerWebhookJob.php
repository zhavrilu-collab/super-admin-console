<?php

namespace App\Jobs;

use App\Models\TenantCustomerWebhook;
use App\Support\CustomerWebhookSignature;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeliverCustomerWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public TenantCustomerWebhook $webhook,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = $this->webhook->fresh();

        if ($webhook === null || ! $webhook->is_active) {
            return;
        }

        $deliveryId = (string) Str::uuid();
        $timestamp = time();
        $body = json_encode([
            'id' => $deliveryId,
            'event' => $this->event,
            'created_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ], JSON_THROW_ON_ERROR);

        $signature = CustomerWebhookSignature::sign($webhook->secret, $timestamp, $body);

        try {
            $response = Http::withHeaders([
                'X-Webhook-Id' => $deliveryId,
                'X-Webhook-Event' => $this->event,
                'X-Webhook-Timestamp' => (string) $timestamp,
                'X-Webhook-Signature' => 'sha256='.$signature,
            ])
                ->withBody($body, 'application/json')
                ->timeout(15)
                ->post($webhook->url);

            if (! $response->successful()) {
                $this->recordFailure($webhook, $response->status());

                throw new \RuntimeException('Customer webhook returned HTTP '.$response->status());
            }

            $webhook->forceFill([
                'last_delivered_at' => now(),
                'failure_count' => 0,
                'last_failed_at' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $this->recordFailure($webhook);

            Log::warning('Customer webhook delivery failed.', [
                'webhook_id' => $webhook->id,
                'tenant_id' => $webhook->tenant_id,
                'event' => $this->event,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function recordFailure(TenantCustomerWebhook $webhook, ?int $status = null): void
    {
        $webhook->forceFill([
            'last_failed_at' => now(),
            'failure_count' => $webhook->failure_count + 1,
        ])->save();

        if ($status !== null) {
            Log::warning('Customer webhook non-success response.', [
                'webhook_id' => $webhook->id,
                'status' => $status,
            ]);
        }
    }
}
