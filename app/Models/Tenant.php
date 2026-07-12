<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
