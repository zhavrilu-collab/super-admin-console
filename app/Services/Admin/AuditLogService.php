<?php

namespace App\Services\Admin;

use App\Enums\AuditAction;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\AuditLog;
use App\Models\ImpersonationSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogService
{
    public function logTenantStatusChanged(
        User $actor,
        Tenant $tenant,
        TenantStatus $from,
        TenantStatus $to,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'application_id' => $tenant->application_id,
            'action' => AuditAction::TenantStatusChanged,
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'properties' => [
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'from' => $from->value,
                'to' => $to->value,
            ],
        ]);
    }

    public function logTenantPlanChanged(
        User $actor,
        Tenant $tenant,
        string $from,
        string $to,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'application_id' => $tenant->application_id,
            'action' => AuditAction::TenantPlanChanged,
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'properties' => [
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function logImpersonationStarted(ImpersonationSession $session): AuditLog
    {
        $session->loadMissing(['admin', 'tenant']);

        return AuditLog::query()->create([
            'user_id' => $session->user_id,
            'application_id' => $session->application_id,
            'action' => AuditAction::ImpersonationStarted,
            'subject_type' => Tenant::class,
            'subject_id' => $session->tenant_id,
            'properties' => [
                'tenant_name' => $session->tenant->name,
                'tenant_slug' => $session->tenant->slug,
                'reason' => $session->reason,
                'session_id' => $session->id,
            ],
        ]);
    }

    public function logImpersonationEnded(ImpersonationSession $session): AuditLog
    {
        $session->loadMissing(['admin', 'tenant']);

        return AuditLog::query()->create([
            'user_id' => $session->user_id,
            'application_id' => $session->application_id,
            'action' => AuditAction::ImpersonationEnded,
            'subject_type' => Tenant::class,
            'subject_id' => $session->tenant_id,
            'properties' => [
                'tenant_name' => $session->tenant->name,
                'tenant_slug' => $session->tenant->slug,
                'session_id' => $session->id,
            ],
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginateForApplication(?int $applicationId, int $perPage = 25): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with(['user', 'application'])
            ->when(
                $applicationId !== null,
                fn ($query) => $query->where('application_id', $applicationId),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginateForTenant(Tenant $tenant, int $perPage = 15): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
