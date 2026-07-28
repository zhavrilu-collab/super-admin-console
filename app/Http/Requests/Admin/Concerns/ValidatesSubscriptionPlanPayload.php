<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\ApplicationFeatureType;
use App\Models\ApplicationFeature;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\ApplicationFeatureCatalogService;
use App\Services\Admin\SubscriptionPlanService;
use Illuminate\Validation\Rule;

trait ValidatesSubscriptionPlanPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function basePlanRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'badge_class' => ['required', Rule::in(['secondary', 'primary', 'dark', 'success', 'warning', 'danger', 'info'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_default' => ['sometimes', 'boolean'],
            'stripe_product_id' => ['nullable', 'string', 'max:255'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'monthly_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'sync_stripe_catalog' => ['sometimes', 'boolean'],
            'features' => ['nullable', 'array'],
            ...$this->featureFieldRules(),
        ];
    }

    public function shouldSyncStripeCatalog(): bool
    {
        return $this->boolean('sync_stripe_catalog');
    }

    /**
     * @return array<string, mixed>
     */
    protected function featureFieldRules(): array
    {
        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();
        $catalog = app(ApplicationFeatureCatalogService::class)->forApplication($applicationId);
        $rules = [];

        foreach ($catalog as $feature) {
            $key = 'features.'.$feature->key;

            if ($feature->type === ApplicationFeatureType::Limit) {
                $rules[$key] = ['nullable', 'integer', 'min:1', 'max:1000000'];

                continue;
            }

            $rules[$key] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPlanPayload(): array
    {
        $data = $this->validated();
        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();
        $planService = app(SubscriptionPlanService::class);
        $catalogService = app(ApplicationFeatureCatalogService::class);

        $features = $catalogService->normalizeFeaturesPayload(
            $applicationId,
            is_array($data['features'] ?? null) ? $data['features'] : [],
        );

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'badge_class' => $data['badge_class'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'features' => $features,
            ...$planService->legacyColumnsFromFeatures($features),
            'stripe_product_id' => $this->filled('stripe_product_id') ? $data['stripe_product_id'] : null,
            'stripe_price_id' => $this->filled('stripe_price_id') ? $data['stripe_price_id'] : null,
            'monthly_price_cents' => $this->monthlyPriceCentsFromInput($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function monthlyPriceCentsFromInput(array $data): ?int
    {
        if (! $this->filled('monthly_price')) {
            return null;
        }

        return (int) round(((float) $data['monthly_price']) * 100);
    }

    protected function prepareFeatureBooleans(): void
    {
        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();
        /** @var \Illuminate\Support\Collection<int, ApplicationFeature> $catalog */
        $catalog = app(ApplicationFeatureCatalogService::class)->forApplication($applicationId);
        $features = $this->input('features', []);

        if (! is_array($features)) {
            $features = [];
        }

        foreach ($catalog as $feature) {
            if ($feature->type !== ApplicationFeatureType::Boolean) {
                continue;
            }

            $features[$feature->key] = $this->boolean('features.'.$feature->key);
        }

        $this->merge(['features' => $features]);
    }
}
