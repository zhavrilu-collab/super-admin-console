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

    public function deleteTenant(Tenant $tenant): void;
}
