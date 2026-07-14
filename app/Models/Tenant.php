<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'external_id',
        'name',
        'slug',
        'status',
        'plan',
        'stripe_customer_id',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'synced_at' => 'datetime',
        ];
    }

    public function resolvedSubscriptionPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('application_id', $this->application_id)
            ->where('slug', $this->plan)
            ->first();
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return HasMany<TenantSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * @return HasOne<TenantSubscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)
            ->whereIn('status', [
                \App\Enums\SubscriptionStatus::Active->value,
                \App\Enums\SubscriptionStatus::Trialing->value,
            ])
            ->latestOfMany();
    }
}
