<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PlatformInvite extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'application_id',
        'tenant_id',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invited_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array{invite: self, plain_token: string}
     */
    public static function issue(
        Application $application,
        Tenant $tenant,
        string $email,
        string $role,
        ?User $invitedBy = null,
        int $ttlDays = 7,
    ): array {
        $plainToken = Str::random(64);

        $invite = static::query()->create([
            'application_id' => $application->id,
            'tenant_id' => $tenant->id,
            'email' => strtolower(trim($email)),
            'role' => $role,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays($ttlDays),
            'invited_by_user_id' => $invitedBy?->id,
        ]);

        return [
            'invite' => $invite,
            'plain_token' => $plainToken,
        ];
    }

    public static function findPendingByPlainToken(string $plainToken): ?self
    {
        $invite = static::query()
            ->with(['tenant', 'application'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('accepted_at')
            ->first();

        if ($invite === null || $invite->isExpired()) {
            return null;
        }

        return $invite;
    }
}
