<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PlatformAccessToken extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'expires_at',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function issueFor(User $user, string $name = 'platform-access'): string
    {
        $plainToken = Str::random(64);
        $ttlHours = max(1, (int) config('identity.token_ttl_hours', 720));

        static::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addHours($ttlHours),
        ]);

        return $plainToken;
    }

    public static function findValidForPlainToken(string $plainToken): ?self
    {
        $token = static::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null) {
            return null;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token->fresh();
    }

    public static function revokePlainToken(string $plainToken): void
    {
        static::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->delete();
    }
}
