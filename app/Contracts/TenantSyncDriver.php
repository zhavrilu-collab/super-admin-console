<?php

namespace App\Contracts;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface TenantSyncDriver
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pullTenants(Application $application): Collection;

    public function pushTenantStatus(Tenant $tenant, TenantStatus $status): void;

    public function pushTenantPlan(Tenant $tenant, string $planSlug): void;

    /**
     * @return array{trial_ends_at: ?string, plan: ?string}
     */
    public function pushTenantTrialExtension(Tenant $tenant, int $days): array;

    public function deleteTenant(Tenant $tenant): void;
}
