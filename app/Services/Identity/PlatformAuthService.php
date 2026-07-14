<?php

namespace App\Services\Identity;

use App\Models\Application;
use App\Models\PlatformAccessToken;
use App\Models\PlatformUserLink;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformAuthService
{
    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Neispravni podaci za prijavu.'],
            ]);
        }

        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['Super-admin računi koriste web prijavu konzole.'],
            ]);
        }

        $token = PlatformAccessToken::issueFor($user);

        return [
            'token' => $token,
            'user' => $this->serializeUser($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeUser(User $user): array
    {
        $user->loadMissing('platformUserLinks.application');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'links' => $user->platformUserLinks->map(fn (PlatformUserLink $link) => [
                'application_slug' => $link->application->slug,
                'external_user_id' => $link->external_user_id,
            ])->values()->all(),
        ];
    }

    public function logout(?string $plainToken): void
    {
        if (! is_string($plainToken) || $plainToken === '') {
            return;
        }

        PlatformAccessToken::revokePlainToken($plainToken);
    }

    public function resolveUserFromPlainToken(string $plainToken): ?User
    {
        $accessToken = PlatformAccessToken::findValidForPlainToken($plainToken);

        return $accessToken?->user;
    }

    /**
     * @param  list<array{external_id: string, name: string, email: string, password: string}>  $users
     * @return list<array{external_id: string, core_user_id: int, email: string}>
     */
    public function importUsers(Application $application, array $users): array
    {
        $imported = [];

        DB::transaction(function () use ($application, $users, &$imported): void {
            foreach ($users as $payload) {
                $user = User::query()->where('email', $payload['email'])->first();

                if ($user === null) {
                    $userId = DB::table('users')->insertGetId([
                        'name' => $payload['name'],
                        'email' => $payload['email'],
                        'password' => $payload['password'],
                        'is_super_admin' => false,
                        'email_verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $user = User::query()->findOrFail($userId);
                } else {
                    $user->name = $payload['name'];
                    $user->is_super_admin = false;
                    if ($user->email_verified_at === null) {
                        $user->email_verified_at = now();
                    }
                    $user->save();

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['password' => $payload['password']]);
                }

                PlatformUserLink::query()->updateOrCreate(
                    [
                        'application_id' => $application->id,
                        'external_user_id' => (string) $payload['external_id'],
                    ],
                    [
                        'user_id' => $user->id,
                    ],
                );

                $imported[] = [
                    'external_id' => (string) $payload['external_id'],
                    'core_user_id' => $user->id,
                    'email' => $user->email,
                ];
            }
        });

        return $imported;
    }

    public function authenticateRequestBearer(?string $bearerToken): ?User
    {
        if (! is_string($bearerToken) || $bearerToken === '') {
            return null;
        }

        $user = $this->resolveUserFromPlainToken($bearerToken);

        if ($user !== null) {
            Auth::setUser($user);
        }

        return $user;
    }
}
