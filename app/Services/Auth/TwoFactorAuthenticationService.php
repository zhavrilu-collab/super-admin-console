<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class TwoFactorAuthenticationService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $otpAuthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($otpAuthUrl);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', $code) ?? '';

        if ($normalizedCode === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $normalizedCode);
    }

    public function verifyForUser(User $user, string $code): bool
    {
        $secret = $this->decryptSecret($user);

        if ($secret === null) {
            return false;
        }

        return $this->verifyCode($secret, $code);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, function (): string {
            return Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        })->all();
    }

    public function enable(User $user, string $secret, string $confirmationCode): array
    {
        if (! $this->verifyCode($secret, $confirmationCode)) {
            throw new RuntimeException('Kôd iz autentifikatora nije ispravan.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $recoveryCodes;
    }

    public function disable(User $user, string $password, string $code): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new RuntimeException('Lozinka nije ispravna.');
        }

        if (! $this->verifyForUser($user, $code) && ! $this->verifyRecoveryCode($user, $code)) {
            throw new RuntimeException('Kôd iz autentifikatora nije ispravan.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->recoveryCodes($user);
        $normalized = Str::upper(preg_replace('/\s+/', '', $code) ?? '');

        if ($normalized === '') {
            return false;
        }

        foreach ($codes as $index => $storedCode) {
            if (! hash_equals($storedCode, $normalized)) {
                continue;
            }

            unset($codes[$index]);
            $user->forceFill([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes))),
            ])->save();

            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function decryptSecret(User $user): ?string
    {
        if ($user->two_factor_secret === null) {
            return null;
        }

        try {
            return Crypt::decryptString($user->two_factor_secret);
        } catch (\Throwable) {
            return null;
        }
    }
}
