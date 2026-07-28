<?php

namespace App\Services\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionPlanService
{
    public function __construct(
        private readonly ApplicationFeatureCatalogService $featureCatalog,
    ) {}

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function forApplication(?int $applicationId): Collection
    {
        if ($applicationId === null) {
            return new Collection;
        }

        return SubscriptionPlan::query()
            ->where('application_id', $applicationId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findForApplication(?int $applicationId, string $slug): ?SubscriptionPlan
    {
        if ($applicationId === null || $slug === '') {
            return null;
        }

        return SubscriptionPlan::query()
            ->where('application_id', $applicationId)
            ->where('slug', $slug)
            ->first();
    }

    public function defaultForApplication(?int $applicationId): ?SubscriptionPlan
    {
        if ($applicationId === null) {
            return null;
        }

        return SubscriptionPlan::query()
            ->where('application_id', $applicationId)
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->first()
            ?? SubscriptionPlan::query()
                ->where('application_id', $applicationId)
                ->orderBy('sort_order')
                ->first();
    }

    /**
     * @return array<string, int>
     */
    public function tenantCountsBySlug(?int $applicationId): array
    {
        if ($applicationId === null) {
            return [];
        }

        return DB::table('tenants')
            ->where('application_id', $applicationId)
            ->selectRaw('plan, COUNT(*) as aggregate')
            ->groupBy('plan')
            ->pluck('aggregate', 'plan')
            ->all();
    }

    public function assertSlugExistsForApplication(?int $applicationId, string $slug): SubscriptionPlan
    {
        $plan = $this->findForApplication($applicationId, $slug);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan' => 'Odabrani paket ne postoji za aktivnu aplikaciju.',
            ]);
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $features
     * @return array<string, mixed>
     */
    public function legacyColumnsFromFeatures(array $features): array
    {
        return [
            'member_limit' => array_key_exists('member_limit', $features) && $features['member_limit'] !== null && $features['member_limit'] !== ''
                ? (int) $features['member_limit']
                : null,
            'subdomain' => (bool) ($features['subdomain'] ?? false),
            'custom_domain' => (bool) ($features['custom_domain'] ?? false),
            'editable_sections' => (bool) ($features['editable_sections'] ?? false),
            'cookie_banner' => (bool) ($features['cookie_banner'] ?? false),
        ];
    }

    public function seedDefaults(Application $application): void
    {
        $this->featureCatalog->seedDefaults($application);

        $defaults = [
            [
                'slug' => 'basic',
                'name' => 'Osnovni',
                'badge_class' => 'secondary',
                'sort_order' => 1,
                'is_default' => true,
                'monthly_price_cents' => 0,
                'features' => $this->featureCatalog->defaultFeaturesForPlanSlug($application, 'basic'),
            ],
            [
                'slug' => 'standard',
                'name' => 'Standardni',
                'badge_class' => 'primary',
                'sort_order' => 2,
                'is_default' => false,
                'monthly_price_cents' => 2900,
                'features' => $this->featureCatalog->defaultFeaturesForPlanSlug($application, 'standard'),
            ],
            [
                'slug' => 'premium',
                'name' => 'Napredni',
                'badge_class' => 'dark',
                'sort_order' => 3,
                'is_default' => false,
                'monthly_price_cents' => 7900,
                'features' => $this->featureCatalog->defaultFeaturesForPlanSlug($application, 'premium'),
            ],
        ];

        $this->upsertSeedPlans($application, $defaults);
    }

    public function seedSmbDefaults(Application $application): void
    {
        $this->featureCatalog->seedDefaults($application);

        $defaults = [
            [
                'slug' => 'basic',
                'name' => 'Starter',
                'badge_class' => 'secondary',
                'sort_order' => 1,
                'is_default' => true,
                'monthly_price_cents' => 0,
                'features' => $this->featureCatalog->defaultFeaturesForPlanSlug($application, 'basic'),
            ],
            [
                'slug' => 'standard',
                'name' => 'Business',
                'badge_class' => 'primary',
                'sort_order' => 2,
                'is_default' => false,
                'monthly_price_cents' => 4900,
                'features' => $this->featureCatalog->defaultFeaturesForPlanSlug($application, 'standard'),
            ],
            [
                'slug' => 'premium',
                'name' => 'Enterprise',
                'badge_class' => 'dark',
                'sort_order' => 3,
                'is_default' => false,
                'monthly_price_cents' => 14900,
                'features' => $this->featureCatalog->defaultFeaturesForPlanSlug($application, 'premium'),
            ],
        ];

        $this->upsertSeedPlans($application, $defaults);
    }

    /**
     * @param  list<array<string, mixed>>  $defaults
     */
    private function upsertSeedPlans(Application $application, array $defaults): void
    {
        foreach ($defaults as $plan) {
            $features = $plan['features'];
            unset($plan['features']);

            SubscriptionPlan::query()->updateOrCreate(
                [
                    'application_id' => $application->id,
                    'slug' => $plan['slug'],
                ],
                [
                    ...$plan,
                    'features' => $features,
                    ...$this->legacyColumnsFromFeatures($features),
                ],
            );
        }
    }

    public function setDefault(SubscriptionPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            SubscriptionPlan::query()
                ->where('application_id', $plan->application_id)
                ->update(['is_default' => false]);

            $plan->is_default = true;
            $plan->save();
        });
    }
}
