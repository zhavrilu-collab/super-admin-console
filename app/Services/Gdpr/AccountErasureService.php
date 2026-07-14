<?php

namespace App\Services\Gdpr;

use App\Enums\AccountDeletionStatus;
use App\Enums\AuditAction;
use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\PlatformAccessToken;
use App\Models\PlatformTenantMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AccountErasureService
{
    public function gracePeriodDays(): int
    {
        return max(1, (int) config('gdpr.erasure_grace_period_days', 14));
    }

    public function pendingRequestFor(User $user): ?AccountDeletionRequest
    {
        return AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AccountDeletionStatus::Pending)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(User $user): array
    {
        $pending = $this->pendingRequestFor($user);

        if ($pending === null) {
            return [
                'status' => 'none',
                'grace_period_days' => $this->gracePeriodDays(),
            ];
        }

        return [
            'status' => $pending->status->value,
            'requested_at' => $pending->requested_at->toIso8601String(),
            'scheduled_deletion_at' => $pending->scheduled_deletion_at->toIso8601String(),
            'grace_period_days' => $this->gracePeriodDays(),
            'days_remaining' => max(0, now()->diffInDays($pending->scheduled_deletion_at, false)),
        ];
    }

    public function requestDeletion(User $user, ?string $ipAddress = null): AccountDeletionRequest
    {
        $this->assertCanRequestDeletion($user);

        if ($this->pendingRequestFor($user) !== null) {
            throw ValidationException::withMessages([
                'account' => ['Zahtjev za brisanje računa je već u tijeku.'],
            ]);
        }

        $requestedAt = now();
        $scheduledAt = $requestedAt->copy()->addDays($this->gracePeriodDays());

        $request = AccountDeletionRequest::query()->create([
            'user_id' => $user->id,
            'status' => AccountDeletionStatus::Pending,
            'requested_at' => $requestedAt,
            'scheduled_deletion_at' => $scheduledAt,
            'ip_address' => $ipAddress,
        ]);

        PlatformAccessToken::query()->where('user_id', $user->id)->delete();

        AuditLog::query()->create([
            'user_id' => $user->id,
            'application_id' => null,
            'action' => AuditAction::AccountDeletionRequested,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'properties' => [
                'email' => $user->email,
                'scheduled_deletion_at' => $scheduledAt->toIso8601String(),
            ],
        ]);

        return $request;
    }

    public function cancelDeletion(User $user): AccountDeletionRequest
    {
        $pending = $this->pendingRequestFor($user);

        if ($pending === null) {
            throw ValidationException::withMessages([
                'account' => ['Nema aktivnog zahtjeva za brisanje računa.'],
            ]);
        }

        $pending->forceFill([
            'status' => AccountDeletionStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        AuditLog::query()->create([
            'user_id' => $user->id,
            'application_id' => null,
            'action' => AuditAction::AccountDeletionCancelled,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'properties' => [
                'email' => $user->email,
            ],
        ]);

        return $pending->fresh();
    }

    public function processDueDeletions(): int
    {
        $processed = 0;

        AccountDeletionRequest::query()
            ->where('status', AccountDeletionStatus::Pending)
            ->where('scheduled_deletion_at', '<=', Carbon::now())
            ->orderBy('id')
            ->with('user')
            ->chunkById(50, function ($requests) use (&$processed): void {
                foreach ($requests as $request) {
                    $user = $request->user;

                    if ($user === null) {
                        $request->forceFill([
                            'status' => AccountDeletionStatus::Completed,
                            'completed_at' => now(),
                        ])->save();

                        continue;
                    }

                    $this->executeDeletion($user, $request);
                    $processed++;
                }
            });

        return $processed;
    }

    public function executeDeletion(User $user, ?AccountDeletionRequest $request = null): void
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'account' => ['Super-admin računi se brišu kroz konzolu.'],
            ]);
        }

        AuditLog::query()->create([
            'user_id' => $user->id,
            'application_id' => null,
            'action' => AuditAction::AccountDeletionCompleted,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'properties' => [
                'email' => $user->email,
            ],
        ]);

        if ($request !== null) {
            $request->forceFill([
                'status' => AccountDeletionStatus::Completed,
                'completed_at' => now(),
            ])->save();
        }

        $user->delete();
    }

    private function assertCanRequestDeletion(User $user): void
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'account' => ['Super-admin računi se brišu kroz konzolu.'],
            ]);
        }

        $soleOwnerTenants = PlatformTenantMembership::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformTenantMembership::ROLE_OWNER)
            ->with('tenant')
            ->get()
            ->filter(function (PlatformTenantMembership $membership) use ($user): bool {
                return ! PlatformTenantMembership::query()
                    ->where('tenant_id', $membership->tenant_id)
                    ->where('role', PlatformTenantMembership::ROLE_OWNER)
                    ->where('user_id', '!=', $user->id)
                    ->exists();
            })
            ->map(fn (PlatformTenantMembership $membership): string => $membership->tenant->name ?? 'Organizacija')
            ->values()
            ->all();

        if ($soleOwnerTenants !== []) {
            throw ValidationException::withMessages([
                'account' => [
                    'Prije brisanja prenesite vlasništvo organizacija: '.implode(', ', $soleOwnerTenants).'.',
                ],
            ]);
        }
    }
}
