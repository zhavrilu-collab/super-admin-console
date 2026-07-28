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
        'features',
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
            'features' => 'array',
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
     * @return array<string, mixed>
     */
    public function featuresMap(): array
    {
        $features = is_array($this->features) ? $this->features : [];

        if ($features === []) {
            return [
                'member_limit' => $this->member_limit,
                'subdomain' => (bool) $this->subdomain,
                'custom_domain' => (bool) $this->custom_domain,
                'editable_sections' => (bool) $this->editable_sections,
                'cookie_banner' => (bool) $this->cookie_banner,
            ];
        }

        return $features;
    }

    public function featureValue(string $key, mixed $default = null): mixed
    {
        $features = $this->featuresMap();

        return array_key_exists($key, $features) ? $features[$key] : $default;
    }

    public function boolFeature(string $key, bool $default = false): bool
    {
        return (bool) $this->featureValue($key, $default);
    }

    public function limitFeature(string $key): ?int
    {
        $value = $this->featureValue($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function isUnlimited(): bool
    {
        return $this->limitFeature('member_limit') === null;
    }

    public function memberLimitLabel(): string
    {
        $limit = $this->limitFeature('member_limit');

        return $limit === null ? 'Neograničeno' : (string) $limit;
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

    /**
     * @return array<string, mixed>
     */
    public function toSyncArray(): array
    {
        $features = $this->featuresMap();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'member_limit' => $features['member_limit'] ?? null,
            'badge_class' => $this->badge_class,
            'sort_order' => $this->sort_order,
            'is_default' => $this->is_default,
            'features' => $features,
            'subdomain' => (bool) ($features['subdomain'] ?? false),
            'custom_domain' => (bool) ($features['custom_domain'] ?? false),
            'editable_sections' => (bool) ($features['editable_sections'] ?? false),
            'cookie_banner' => (bool) ($features['cookie_banner'] ?? false),
        ];
    }
}
