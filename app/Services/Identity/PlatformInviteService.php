<?php

namespace App\Services\Identity;

use App\Models\Application;
use App\Models\PlatformAccessToken;
use App\Models\PlatformInvite;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Identity\PlatformWorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformInviteService
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
        private readonly PlatformWorkspaceService $workspaces,
    ) {}

    /**
     * @return array{invite: PlatformInvite, plain_token: string}
     */
    public function create(
        Application $application,
        Tenant $tenant,
        string $email,
        string $role,
        ?User $invitedBy = null,
    ): array {
        $email = strtolower(trim($email));

        if (! in_array($role, [PlatformInvite::ROLE_OWNER, PlatformInvite::ROLE_ADMIN], true)) {
            throw ValidationException::withMessages([
                'role' => ['Uloga mora biti owner ili admin.'],
            ]);
        }

        PlatformInvite::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        return PlatformInvite::issue($application, $tenant, $email, $role, $invitedBy);
    }

    /**
     * @return array{user: User, token: string, invite: PlatformInvite}
     */
    public function accept(string $plainToken, string $name, string $password): array
    {
        $invite = PlatformInvite::findPendingByPlainToken($plainToken);

        if ($invite === null) {
            throw ValidationException::withMessages([
                'token' => ['Pozivnica nije valjana ili je istekla.'],
            ]);
        }

        return DB::transaction(function () use ($invite, $name, $password): array {
            $hashedPassword = Hash::make($password);
            $user = User::query()->where('email', $invite->email)->first();

            if ($user === null) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $name,
                    'email' => $invite->email,
                    'password' => $hashedPassword,
                    'is_super_admin' => false,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $user = User::query()->findOrFail($userId);
            } else {
                $user->name = $name;
                $user->is_super_admin = false;
                $user->email_verified_at ??= now();
                $user->save();

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => $hashedPassword]);
            }

            $invite->accepted_at = now();
            $invite->save();

            $this->workspaces->upsertMembership($user, $invite->tenant, $invite->role);

            $token = PlatformAccessToken::issueFor($user, 'invite-accept');

            return [
                'user' => $user->fresh(),
                'token' => $token,
                'invite' => $invite->fresh(['tenant', 'application']),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeInvite(PlatformInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'email' => $invite->email,
            'role' => $invite->role,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'accepted_at' => $invite->accepted_at?->toIso8601String(),
            'tenant' => [
                'external_id' => $invite->tenant->external_id,
                'slug' => $invite->tenant->slug,
                'name' => $invite->tenant->name,
            ],
        ];
    }
}
