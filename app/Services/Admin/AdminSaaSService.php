<?php

namespace App\Services\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminSaaSService
{
    public function __construct(
        private readonly TenantSyncService $tenantSyncService,
        private readonly AuditLogService $auditLogService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}
    public function getActiveApplicationId(): ?int
    {
        $id = Session::get(AdminSession::ACTIVE_APP_ID);

        return $id !== null ? (int) $id : null;
    }

    public function getActiveApplication(): ?Application
    {
        $applicationId = $this->getActiveApplicationId();

        if ($applicationId === null) {
            return null;
        }

        return Application::query()->find($applicationId);
    }

    /**
     * @return Collection<int, Application>
     */
    public function getAllApplications(): Collection
    {
        return Application::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function getTenants(): Collection
    {
        $applicationId = $this->getActiveApplicationId();

        if ($applicationId === null) {
            return new Collection();
        }

        return Tenant::query()
            ->where('application_id', $applicationId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    public function bulkUpdateTenantStatus(array $tenantIds, TenantStatus $status, User $actor): array
    {
        $applicationId = $this->getActiveApplicationId();

        if ($applicationId === null) {
            throw new NotFoundHttpException('Nema aktivne aplikacije.');
        }

        $tenants = Tenant::query()
            ->where('application_id', $applicationId)
            ->whereIn('id', $tenantIds)
            ->orderBy('name')
            ->get();

        if ($tenants->count() !== count($tenantIds)) {
            throw new NotFoundHttpException('Jedan ili više tenanata nije pronađen za aktivnu aplikaciju.');
        }

        $updated = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->status === $status) {
                $skipped++;

                continue;
            }

            $this->updateTenantStatus($tenant->id, $status, $actor);
            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     suspended: int,
     *     pending: int,
     *     plans: array<string, int>
     * }
     */
    public function getDashboardStats(): array
    {
        $applicationId = $this->getActiveApplicationId();

        if ($applicationId === null) {
            return [
                'total' => 0,
                'active' => 0,
                'suspended' => 0,
                'pending' => 0,
                'plans' => [],
            ];
        }

        $baseQuery = Tenant::query()->where('application_id', $applicationId);
        $planCounts = $this->subscriptionPlanService->tenantCountsBySlug($applicationId);

        foreach ($this->subscriptionPlanService->forApplication($applicationId) as $plan) {
            $planCounts[$plan->slug] ??= 0;
        }

        ksort($planCounts);

        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', TenantStatus::Active)->count(),
            'suspended' => (clone $baseQuery)->where('status', TenantStatus::Suspended)->count(),
            'pending' => (clone $baseQuery)->where('status', TenantStatus::Pending)->count(),
            'plans' => $planCounts,
        ];
    }

    public function updateTenantStatus(int $tenantId, TenantStatus $status, User $actor): Tenant
    {
        $tenant = $this->findTenantForActiveApp($tenantId);
        $previousStatus = $tenant->status;

        if ($previousStatus === $status) {
            return $tenant;
        }

        $tenant->load('application');
        $this->tenantSyncService->pushStatus($tenant, $status);

        $tenant->status = $status;
        $tenant->save();
        $this->auditLogService->logTenantStatusChanged($actor, $tenant, $previousStatus, $status);

        return $tenant;
    }

    public function changeTenantPlan(int $tenantId, string $planSlug, User $actor): Tenant
    {
        $tenant = $this->findTenantForActiveApp($tenantId);
        $previousPlan = $tenant->plan;

        if ($previousPlan === $planSlug) {
            return $tenant;
        }

        $this->subscriptionPlanService->assertSlugExistsForApplication(
            $this->getActiveApplicationId(),
            $planSlug,
        );

        $tenant->load('application');
        $this->tenantSyncService->pushPlan($tenant, $planSlug);

        $tenant->plan = $planSlug;
        $tenant->save();
        $this->auditLogService->logTenantPlanChanged($actor, $tenant, $previousPlan, $planSlug);

        return $tenant;
    }

    public function deleteTenant(int $tenantId, User $actor): void
    {
        $tenant = $this->findTenantForActiveApp($tenantId);
        $tenant->load('application');

        if ($tenant->application !== null && $this->tenantSyncService->isConfigured($tenant->application)) {
            $this->tenantSyncService->deleteRemote($tenant);
        }

        $this->auditLogService->logTenantDeleted($actor, $tenant);
        $tenant->delete();
    }

    public function syncActiveApplication(): int
    {
        $application = $this->getActiveApplication();

        if ($application === null) {
            return 0;
        }

        if (! $this->tenantSyncService->isConfigured($application)) {
            throw new NotFoundHttpException('Aktivna aplikacija ne podržava API sinkronizaciju.');
        }

        return $this->tenantSyncService->pullForApplication($application);
    }

    private function findTenantForActiveApp(int $tenantId): Tenant
    {
        $applicationId = $this->getActiveApplicationId();

        $tenant = Tenant::query()
            ->where('id', $tenantId)
            ->where('application_id', $applicationId)
            ->first();

        if ($tenant === null) {
            throw new NotFoundHttpException('Tenant nije pronađen za aktivnu aplikaciju.');
        }

        return $tenant;
    }

    public function getTenantForActiveApp(int $tenantId): Tenant
    {
        return $this->findTenantForActiveApp($tenantId);
    }
}
