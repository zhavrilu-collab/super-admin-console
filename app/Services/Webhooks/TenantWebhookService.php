<?php

namespace App\Services\Webhooks;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Services\Admin\SuperAdminNotificationService;

class TenantWebhookService
{
    public function __construct(
        private readonly SuperAdminNotificationService $superAdminNotificationService,
    ) {}
    /**
     * @param  array{
     *     application_slug: string,
     *     organization: array{
     *         id: int,
     *         name: string,
     *         slug: string,
     *         status: string,
     *         plan: string,
     *         email?: string|null
     *     }
     * }  $payload
     */
    public function handleTenantRegistered(array $payload): Tenant
    {
        $application = Application::query()
            ->where('slug', $payload['application_slug'])
            ->firstOrFail();

        $organization = $payload['organization'];

        /** @var TenantStatus $status */
        $status = TenantStatus::from($organization['status']);

        $tenant = Tenant::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'external_id' => (string) $organization['id'],
            ],
            [
                'name' => $organization['name'],
                'slug' => $organization['slug'],
                'status' => $status,
                'plan' => (string) $organization['plan'],
                'synced_at' => now(),
            ],
        );

        if ($tenant->wasRecentlyCreated && $status === TenantStatus::Pending) {
            $this->superAdminNotificationService->notifyNewPendingTenant(
                $tenant,
                isset($organization['email']) ? (string) $organization['email'] : null,
            );
        }

        return $tenant;
    }
}
