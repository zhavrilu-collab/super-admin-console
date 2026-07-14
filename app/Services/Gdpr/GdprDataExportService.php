<?php

namespace App\Services\Gdpr;

use App\Models\GdprExportLog;
use App\Models\OAuthIdentity;
use App\Models\PlatformAccessToken;
use App\Models\PlatformUserLink;
use App\Models\User;
use App\Services\Identity\PlatformWorkspaceService;
use Illuminate\Support\Carbon;

class GdprDataExportService
{
    public function __construct(
        private readonly PlatformWorkspaceService $workspaces,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildExport(User $user): array
    {
        $user->loadMissing(['platformUserLinks.application', 'oauthIdentities']);

        return [
            'exported_at' => now()->toIso8601String(),
            'scope' => 'platform',
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'oauth_identities' => $user->oauthIdentities->map(fn (OAuthIdentity $identity): array => [
                'provider' => $identity->provider,
                'provider_email' => $identity->provider_email,
                'linked_at' => $identity->created_at?->toIso8601String(),
            ])->values()->all(),
            'application_links' => $user->platformUserLinks->map(fn (PlatformUserLink $link): array => [
                'application_slug' => $link->application->slug,
                'application_name' => $link->application->name,
                'external_user_id' => $link->external_user_id,
            ])->values()->all(),
            'workspaces' => $this->workspaces->workspacesForUser($user),
            'active_sessions' => $this->activeSessionMetadata($user),
        ];
    }

    public function recentExportCount(User $user, int $hours = 1): int
    {
        return GdprExportLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->count();
    }

    public function logExport(User $user, string $format, ?string $ipAddress, string $scope = 'platform'): void
    {
        GdprExportLog::query()->create([
            'user_id' => $user->id,
            'format' => $format,
            'scope' => $scope,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeSessionMetadata(User $user): array
    {
        return PlatformAccessToken::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_used_at')
            ->get(['name', 'expires_at', 'last_used_at', 'created_at'])
            ->map(fn (PlatformAccessToken $token): array => [
                'name' => $token->name,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
