<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class ConsoleSettingsService
{
    public const MAIL_MAILER = 'mail.mailer';

    public const MAIL_HOST = 'mail.host';

    public const MAIL_PORT = 'mail.port';

    public const MAIL_USERNAME = 'mail.username';

    public const MAIL_PASSWORD = 'mail.password';

    public const MAIL_ENCRYPTION = 'mail.encryption';

    public const MAIL_FROM_ADDRESS = 'mail.from_address';

    public const MAIL_FROM_NAME = 'mail.from_name';

    public const WEBHOOK_SECRET = 'webhook.secret';

    /** @var list<string> */
    private const ENCRYPTED_KEYS = [
        self::MAIL_PASSWORD,
        self::WEBHOOK_SECRET,
    ];

    public function get(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        $setting = Setting::query()->where('key', $key)->first();

        if ($setting === null || $setting->value === null || $setting->value === '') {
            return $default;
        }

        if ($this->isEncryptedKey($key)) {
            try {
                return Crypt::decryptString($setting->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $setting->value;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            Setting::query()->where('key', $key)->delete();

            return;
        }

        $storedValue = $this->isEncryptedKey($key)
            ? Crypt::encryptString($value)
            : $value;

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue],
        );
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function applyMailConfiguration(): void
    {
        $mailer = $this->get(self::MAIL_MAILER);

        if ($mailer === null) {
            return;
        }

        config(['mail.default' => $mailer]);

        if ($mailer !== 'smtp') {
            return;
        }

        $smtp = array_filter([
            'transport' => 'smtp',
            'host' => $this->get(self::MAIL_HOST),
            'port' => $this->get(self::MAIL_PORT),
            'username' => $this->get(self::MAIL_USERNAME),
            'password' => $this->get(self::MAIL_PASSWORD),
            'encryption' => $this->get(self::MAIL_ENCRYPTION) ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($smtp !== []) {
            config(['mail.mailers.smtp' => array_merge(config('mail.mailers.smtp'), $smtp)]);
        }

        $fromAddress = $this->get(self::MAIL_FROM_ADDRESS);
        $fromName = $this->get(self::MAIL_FROM_NAME);

        if ($fromAddress !== null) {
            config([
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName ?? config('app.name'),
            ]);
        }
    }

    public function webhookSecret(): ?string
    {
        return $this->get(self::WEBHOOK_SECRET, config('webhook.secret'));
    }

    /**
     * @return array<string, string|null>
     */
    public function mailSettingsForForm(): array
    {
        return [
            'mail_mailer' => $this->get(self::MAIL_MAILER, config('mail.default')),
            'mail_host' => $this->get(self::MAIL_HOST, ''),
            'mail_port' => $this->get(self::MAIL_PORT, '587'),
            'mail_username' => $this->get(self::MAIL_USERNAME, ''),
            'mail_encryption' => $this->get(self::MAIL_ENCRYPTION, 'tls'),
            'mail_from_address' => $this->get(self::MAIL_FROM_ADDRESS, ''),
            'mail_from_name' => $this->get(self::MAIL_FROM_NAME, config('app.name')),
        ];
    }

    public function isEncryptedKey(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }
}
