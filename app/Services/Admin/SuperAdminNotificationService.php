<?php

namespace App\Services\Admin;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PendingTenantRegisteredNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SuperAdminNotificationService
{
    public function notifyNewPendingTenant(Tenant $tenant, ?string $contactEmail = null): void
    {
        if ($tenant->status !== TenantStatus::Pending) {
            return;
        }

        $tenant->loadMissing('application');
        $superAdmins = $this->superAdmins();

        if ($superAdmins->isEmpty()) {
            Log::warning('Nema super-admin korisnika za obavijest o novom tenantu.', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        Notification::send(
            $superAdmins,
            new PendingTenantRegisteredNotification($tenant, $contactEmail),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function superAdmins(): Collection
    {
        return User::query()
            ->where('is_super_admin', true)
            ->orderBy('id')
            ->get();
    }
}
