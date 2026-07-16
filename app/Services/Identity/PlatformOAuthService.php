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
        return $this->isProviderConfigured(OAuthProvider::Google);
    }

    public function isMicrosoftConfigured(): bool
    {
        return $this->isProviderConfigured(OAuthProvider::Microsoft);
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

    public function validatePlatformLoginReturnUrl(string $returnUrl): void
    {
        $platformBase = rtrim((string) config('app.url'), '/');
        $normalizedReturn = rtrim($returnUrl, '/');

        if ($normalizedReturn !== $platformBase && ! str_starts_with($normalizedReturn, $platformBase.'/')) {
            throw ValidationException::withMessages([
                'return_url' => ['Povratni URL mora pripadati platformi.'],
            ]);
        }
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function loginFromGoogle(SocialiteUser $googleUser): array
    {
        return $this->loginFromProvider(OAuthProvider::Google, $googleUser);
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function loginFromMicrosoft(SocialiteUser $microsoftUser): array
    {
        return $this->loginFromProvider(OAuthProvider::Microsoft, $microsoftUser);
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function loginFromProvider(OAuthProvider $provider, SocialiteUser $oauthUser): array
    {
        $user = $this->resolveUserFromProvider($provider, $oauthUser);

        return [
            'token' => PlatformAccessToken::issueFor($user, $provider->value.'-oauth'),
            'user' => app(PlatformAuthService::class)->serializeUser($user),
        ];
    }

    /**
     * @return array{two_factor_required?: bool, user?: User, token?: string, user_payload?: array<string, mixed>}
     */
    public function authenticateFromProvider(OAuthProvider $provider, SocialiteUser $oauthUser): array
    {
        $user = $this->resolveUserFromProvider($provider, $oauthUser);

        if ($user->hasTwoFactorEnabled()) {
            return [
                'two_factor_required' => true,
                'user' => $user,
            ];
        }

        $session = app(PlatformAuthService::class)->issueAuthenticatedSession($user, $provider->value.'-oauth');

        return [
            'token' => $session['token'],
            'user_payload' => $session['user'],
        ];
    }

    public function resolveUserFromProvider(OAuthProvider $provider, SocialiteUser $oauthUser): User
    {
        if (! is_string($oauthUser->getId()) || $oauthUser->getId() === '') {
            throw ValidationException::withMessages([
                $provider->value => [$provider->label().' račun nije valjan.'],
            ]);
        }

        $email = $oauthUser->getEmail();

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                $provider->value => [$provider->label().' račun nema e-mail adresu.'],
            ]);
        }

        return DB::transaction(function () use ($provider, $oauthUser, $email): User {
            $identity = OAuthIdentity::query()
                ->where('provider', $provider->value)
                ->where('provider_id', $oauthUser->getId())
                ->first();

            if ($identity !== null) {
                $identity->forceFill([
                    'provider_email' => $email,
                    'avatar' => $oauthUser->getAvatar(),
                ])->save();

                return $identity->user()->firstOrFail();
            }

            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                if ($user->isSuperAdmin()) {
                    throw ValidationException::withMessages([
                        $provider->value => ['Super-admin računi koriste web prijavu konzole.'],
                    ]);
                }

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            } else {
                $user = User::query()->create([
                    'name' => $this->resolveDisplayName($oauthUser, $email),
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                    'is_super_admin' => false,
                ]);
            }

            OAuthIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => $provider->value,
                'provider_id' => $oauthUser->getId(),
                'provider_email' => $email,
                'avatar' => $oauthUser->getAvatar(),
            ]);

            return $user->fresh();
        });
    }

    private function isProviderConfigured(OAuthProvider $provider): bool
    {
        $configKey = $provider->value;

        return filled(config('services.'.$configKey.'.client_id'))
            && filled(config('services.'.$configKey.'.client_secret'))
            && filled(config('services.'.$configKey.'.redirect'));
    }

    private function resolveDisplayName(SocialiteUser $oauthUser, string $email): string
    {
        $name = $oauthUser->getName();

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return Str::before($email, '@');
    }
}
