<?php

namespace App\Models;

use App\Enums\DunningResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionDunningCase extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'contact_email',
        'started_at',
        'suspend_after_at',
        'reminder_stage',
        'last_reminder_at',
        'resolved_at',
        'resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'suspend_after_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolution' => DunningResolution::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<TenantSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }
}
