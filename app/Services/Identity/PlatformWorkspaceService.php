<?php

namespace App\Services\Identity;

use App\Models\Application;
use App\Models\PlatformTenantMembership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlatformWorkspaceService
{
    /**
     * @param  list<array{external_user_id: string, tenant_external_id: string, role: string}>  $memberships
     * @return list<array{external_user_id: string, tenant_external_id: string, role: string}>
     */
    public function syncMemberships(Application $application, array $memberships): array
    {
        $synced = [];

        foreach ($memberships as $payload) {
            $externalUserId = (string) ($payload['external_user_id'] ?? '');
            $tenantExternalId = (string) ($payload['tenant_external_id'] ?? '');
            $role = (string) ($payload['role'] ?? '');

            if ($externalUserId === '' || $tenantExternalId === '' || $role === '') {
                continue;
            }

            if (! in_array($role, [PlatformTenantMembership::ROLE_OWNER, PlatformTenantMembership::ROLE_ADMIN], true)) {
                throw ValidationException::withMessages([
                    'memberships' => ['Uloga mora biti owner ili admin.'],
                ]);
            }

            $link = \App\Models\PlatformUserLink::query()
                ->where('application_id', $application->id)
                ->where('external_user_id', $externalUserId)
                ->first();

            if ($link === null) {
                continue;
            }

            $tenant = Tenant::query()
                ->where('application_id', $application->id)
                ->where('external_id', $tenantExternalId)
                ->first();

            if ($tenant === null) {
                continue;
            }

            PlatformTenantMembership::query()->updateOrCreate(
                [
                    'user_id' => $link->user_id,
                    'tenant_id' => $tenant->id,
                ],
                [
                    'role' => $role,
                ],
            );

            $synced[] = [
                'external_user_id' => $externalUserId,
                'tenant_external_id' => $tenantExternalId,
                'role' => $role,
            ];
        }

        return $synced;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function workspacesForUser(User $user, ?Application $application = null): array
    {
        $query = PlatformTenantMembership::query()
            ->with(['tenant.application'])
            ->where('user_id', $user->id);

        if ($application !== null) {
            $query->whereHas('tenant', fn ($builder) => $builder->where('application_id', $application->id));
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (PlatformTenantMembership $membership) => $this->serializeMembership($membership))
            ->values()
            ->all();
    }

    public function upsertMembership(User $user, Tenant $tenant, string $role): PlatformTenantMembership
    {
        if (! in_array($role, [PlatformTenantMembership::ROLE_OWNER, PlatformTenantMembership::ROLE_ADMIN], true)) {
            throw ValidationException::withMessages([
                'role' => ['Uloga mora biti owner ili admin.'],
            ]);
        }

        return PlatformTenantMembership::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'role' => $role,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMembership(PlatformTenantMembership $membership): array
    {
        $tenant = $membership->tenant;

        return [
            'role' => $membership->role,
            'application_slug' => $tenant->application->slug,
            'tenant' => [
                'external_id' => $tenant->external_id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'plan' => $tenant->plan,
                'sso_enforced' => (bool) $tenant->sso_enforced,
            ],
        ];
    }
}
