<?php

namespace App\Services\Identity;

use App\Models\Application;
use App\Models\PlatformAccessToken;
use App\Models\PlatformUserLink;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformAuthService
{
    private const TWO_FACTOR_CHALLENGE_TTL_SECONDS = 600;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    /**
     * @return array{token?: string, user?: array<string, mixed>, two_factor_required?: bool, two_factor_token?: string}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->authenticateCredentials($email, $password);

        if ($user->hasTwoFactorEnabled()) {
            return [
                'two_factor_required' => true,
                'two_factor_token' => $this->createTwoFactorChallengeToken($user),
            ];
        }

        return $this->issueAuthenticatedSession($user);
    }

    public function authenticateCredentials(string $email, string $password): User
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

        return $user;
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function issueAuthenticatedSession(User $user, string $tokenName = 'platform-access'): array
    {
        return [
            'token' => PlatformAccessToken::issueFor($user, $tokenName),
            'user' => $this->serializeUser($user),
        ];
    }

    public function createTwoFactorChallengeToken(User $user): string
    {
        $plainToken = Str::random(64);

        Cache::put(
            $this->twoFactorChallengeCacheKey($plainToken),
            $user->id,
            now()->addSeconds(self::TWO_FACTOR_CHALLENGE_TTL_SECONDS),
        );

        return $plainToken;
    }

    public function resolveTwoFactorChallengeUser(string $challengeToken): ?User
    {
        if ($challengeToken === '') {
            return null;
        }

        $userId = Cache::get($this->twoFactorChallengeCacheKey($challengeToken));

        if (! is_int($userId) && ! is_numeric($userId)) {
            return null;
        }

        $user = User::query()->find((int) $userId);

        if ($user === null || $user->isSuperAdmin() || ! $user->hasTwoFactorEnabled()) {
            return null;
        }

        return $user;
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function completeTwoFactorChallenge(
        string $challengeToken,
        ?string $code = null,
        ?string $recoveryCode = null,
    ): array {
        $user = $this->resolveTwoFactorChallengeUser($challengeToken);

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => ['2FA sesija je istekla. Prijavite se ponovno.'],
            ]);
        }

        $passed = false;

        if (is_string($recoveryCode) && $recoveryCode !== '') {
            $passed = $this->twoFactor->verifyRecoveryCode($user, $recoveryCode);
        } elseif (is_string($code) && $code !== '') {
            $passed = $this->twoFactor->verifyForUser($user, $code);
        }

        if (! $passed) {
            throw ValidationException::withMessages([
                'code' => ['Kôd nije ispravan.'],
            ]);
        }

        Cache::forget($this->twoFactorChallengeCacheKey($challengeToken));

        return $this->issueAuthenticatedSession($user);
    }

    private function twoFactorChallengeCacheKey(string $plainToken): string
    {
        return 'platform.2fa.challenge.'.hash('sha256', $plainToken);
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function register(string $name, string $email, string $password): array
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Račun s tim e-mailom već postoji. Prijavite se pa registrirajte tvrtku.'],
            ]);
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);

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
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
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
