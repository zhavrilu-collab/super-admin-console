<?php

namespace App\Services\Identity;

use App\Enums\OAuthProvider;
use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\PlatformAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class PlatformOAuthService
{
    public function isGoogleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function validateReturnUrl(Application $application, string $returnUrl): void
    {
        $baseUrl = rtrim((string) $application->api_base_url, '/');

        if ($baseUrl === '') {
            throw ValidationException::withMessages([
                'return_url' => ['Aplikacija nema konfiguriran API URL za povratnu prijavu.'],
            ]);
        }

        $normalizedReturn = rtrim($returnUrl, '/');

        if ($normalizedReturn !== $baseUrl && ! str_starts_with($normalizedReturn, $baseUrl.'/')) {
            throw ValidationException::withMessages([
                'return_url' => ['Povratni URL mora pripadati registriranoj aplikaciji.'],
            ]);
        }
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function loginFromGoogle(SocialiteUser $googleUser): array
    {
        if (! is_string($googleUser->getId()) || $googleUser->getId() === '') {
            throw ValidationException::withMessages([
                'google' => ['Google račun nije valjan.'],
            ]);
        }

        $email = $googleUser->getEmail();

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'google' => ['Google račun nema e-mail adresu.'],
            ]);
        }

        $user = DB::transaction(function () use ($googleUser, $email): User {
            $identity = OAuthIdentity::query()
                ->where('provider', OAuthProvider::Google->value)
                ->where('provider_id', $googleUser->getId())
                ->first();

            if ($identity !== null) {
                $identity->forceFill([
                    'provider_email' => $email,
                    'avatar' => $googleUser->getAvatar(),
                ])->save();

                return $identity->user()->firstOrFail();
            }

            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                if ($user->isSuperAdmin()) {
                    throw ValidationException::withMessages([
                        'google' => ['Super-admin računi koriste web prijavu konzole.'],
                    ]);
                }

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            } else {
                $user = User::query()->create([
                    'name' => $this->resolveDisplayName($googleUser, $email),
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                    'is_super_admin' => false,
                ]);
            }

            OAuthIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => OAuthProvider::Google->value,
                'provider_id' => $googleUser->getId(),
                'provider_email' => $email,
                'avatar' => $googleUser->getAvatar(),
            ]);

            return $user->fresh();
        });

        $token = PlatformAccessToken::issueFor($user, 'google-oauth');

        return [
            'token' => $token,
            'user' => app(PlatformAuthService::class)->serializeUser($user),
        ];
    }

    private function resolveDisplayName(SocialiteUser $googleUser, string $email): string
    {
        $name = $googleUser->getName();

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return Str::before($email, '@');
    }
}
