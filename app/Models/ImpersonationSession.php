<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ImpersonationSession extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'application_id',
        'token_hash',
        'reason',
        'started_at',
        'expires_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array{session: self, plain_token: string}
     */
    public static function issue(User $admin, Tenant $tenant, ?string $reason = null, int $ttlMinutes = 30): array
    {
        $plainToken = Str::random(64);

        $session = static::query()->create([
            'user_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'application_id' => $tenant->application_id,
            'token_hash' => hash('sha256', $plainToken),
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
        ]);

        return [
            'session' => $session,
            'plain_token' => $plainToken,
        ];
    }

    public static function findActiveByPlainToken(string $plainToken): ?self
    {
        $session = static::query()
            ->with(['admin', 'tenant.application'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($session === null || ! $session->isActive()) {
            return null;
        }

        return $session;
    }
}
