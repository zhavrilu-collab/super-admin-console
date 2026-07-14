<?php

namespace App\Services\Identity;

use App\Models\ImpersonationSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformImpersonationService
{
    /**
     * @return array{session: ImpersonationSession, plain_token: string, redirect_url: string|null}
     */
    public function start(User $admin, Tenant $tenant, ?string $reason = null): array
    {
        if (! $admin->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'tenant' => ['Samo super-admin može pokrenuti impersonation.'],
            ]);
        }

        $issued = ImpersonationSession::issue($admin, $tenant, $reason);

        $baseUrl = $tenant->application?->api_base_url;
        $redirectUrl = null;

        if (is_string($baseUrl) && $baseUrl !== '' && $tenant->slug !== '') {
            $redirectUrl = rtrim($baseUrl, '/').'/impersonacija/'.$issued['plain_token'];
        }

        return [
            'session' => $issued['session'],
            'plain_token' => $issued['plain_token'],
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSession(ImpersonationSession $session): array
    {
        $session->loadMissing(['admin', 'tenant.application']);

        return [
            'id' => $session->id,
            'reason' => $session->reason,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'admin' => [
                'id' => $session->admin->id,
                'name' => $session->admin->name,
                'email' => $session->admin->email,
            ],
            'tenant' => [
                'external_id' => $session->tenant->external_id,
                'slug' => $session->tenant->slug,
                'name' => $session->tenant->name,
                'status' => $session->tenant->status,
            ],
            'application_slug' => $session->tenant->application->slug,
        ];
    }

    public function markStarted(ImpersonationSession $session): ImpersonationSession
    {
        if ($session->started_at === null) {
            $session->started_at = now();
            $session->save();
        }

        return $session->fresh(['admin', 'tenant.application']);
    }

    public function end(string $plainToken): ImpersonationSession
    {
        $session = ImpersonationSession::findActiveByPlainToken($plainToken);

        if ($session === null) {
            throw ValidationException::withMessages([
                'token' => ['Impersonation sesija nije valjana ili je istekla.'],
            ]);
        }

        $session->ended_at = now();
        $session->save();

        return $session->fresh(['admin', 'tenant.application']);
    }
}
