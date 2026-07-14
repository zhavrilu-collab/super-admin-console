<?php

namespace App\Models;

use App\Enums\AccountDeletionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'requested_at',
        'scheduled_deletion_at',
        'cancelled_at',
        'completed_at',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountDeletionStatus::class,
            'requested_at' => 'datetime',
            'scheduled_deletion_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === AccountDeletionStatus::Pending;
    }
}
