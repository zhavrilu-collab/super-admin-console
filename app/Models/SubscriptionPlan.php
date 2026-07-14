<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'application_id',
        'slug',
        'name',
        'member_limit',
        'badge_class',
        'sort_order',
        'is_default',
        'subdomain',
        'custom_domain',
        'editable_sections',
        'cookie_banner',
        'stripe_product_id',
        'stripe_price_id',
        'monthly_price_cents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_limit' => 'integer',
            'sort_order' => 'integer',
            'monthly_price_cents' => 'integer',
            'is_default' => 'boolean',
            'subdomain' => 'boolean',
            'custom_domain' => 'boolean',
            'editable_sections' => 'boolean',
            'cookie_banner' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isUnlimited(): bool
    {
        return $this->member_limit === null;
    }

    public function memberLimitLabel(): string
    {
        return $this->isUnlimited() ? 'Neograničeno' : (string) $this->member_limit;
    }

    public function monthlyPriceLabel(): string
    {
        if ($this->monthly_price_cents === null || $this->monthly_price_cents <= 0) {
            return '—';
        }

        return number_format($this->monthly_price_cents / 100, 2, ',', '.').' €';
    }

    public function tenantCount(): int
    {
        return Tenant::query()
            ->where('application_id', $this->application_id)
            ->where('plan', $this->slug)
            ->count();
    }
}
