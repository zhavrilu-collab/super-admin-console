<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sync_driver',
        'api_base_url',
        'api_sync_key',
        'last_synced_at',
    ];

    protected $hidden = [
        'api_sync_key',
    ];

    protected function casts(): array
    {
        return [
            'api_sync_key' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SubscriptionPlan, $this>
     */
    public function subscriptionPlans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
