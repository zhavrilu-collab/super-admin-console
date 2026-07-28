<?php

namespace App\Services\Admin;

use App\Enums\ApplicationFeatureType;
use App\Enums\TenantStatus;
use App\Models\ApplicationFeature;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Billing\BillingMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminStatsService
{
    public function __construct(
        private readonly ApplicationFeatureCatalogService $featureCatalog,
        private readonly BillingMetricsService $billingMetrics,
    ) {}

    /**
     * @return array{
     *     tenants_by_plan: list<array{key: string, label: string, count: int}>,
     *     tenants_by_status: list<array{key: string, label: string, count: int}>,
     *     feature_coverage: list<array{key: string, label: string, count: int, percent: float}>,
     *     mrr_by_plan: list<array{key: string, label: string, mrr_cents: int, subscriptions: int}>,
     *     total_tenants: int,
     * }
     */
    public function aggregates(?int $applicationId): array
    {
        if ($applicationId === null) {
            return [
                'tenants_by_plan' => [],
                'tenants_by_status' => [],
                'feature_coverage' => [],
                'mrr_by_plan' => [],
                'total_tenants' => 0,
            ];
        }

        $totalTenants = Tenant::query()->where('application_id', $applicationId)->count();
        $plans = SubscriptionPlan::query()
            ->where('application_id', $applicationId)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('slug');

        $planCounts = DB::table('tenants')
            ->where('application_id', $applicationId)
            ->selectRaw('plan, COUNT(*) as aggregate')
            ->groupBy('plan')
            ->pluck('aggregate', 'plan');

        $tenantsByPlan = $plans->map(function (SubscriptionPlan $plan) use ($planCounts): array {
            return [
                'key' => $plan->slug,
                'label' => $plan->name,
                'count' => (int) ($planCounts[$plan->slug] ?? 0),
            ];
        })->values()->all();

        foreach ($planCounts as $slug => $count) {
            if ($plans->has($slug)) {
                continue;
            }

            $tenantsByPlan[] = [
                'key' => (string) $slug,
                'label' => (string) $slug,
                'count' => (int) $count,
            ];
        }

        $statusCounts = DB::table('tenants')
            ->where('application_id', $applicationId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tenantsByStatus = collect(TenantStatus::cases())->map(function (TenantStatus $status) use ($statusCounts): array {
            return [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ];
        })->values()->all();

        $featureCoverage = [];
        $catalog = $this->featureCatalog->forApplication($applicationId)
            ->filter(fn (ApplicationFeature $feature) => $feature->type === ApplicationFeatureType::Boolean);

        foreach ($catalog as $feature) {
            $matchingSlugs = $plans
                ->filter(fn (SubscriptionPlan $plan) => $plan->boolFeature($feature->key))
                ->keys()
                ->all();

            $count = $matchingSlugs === []
                ? 0
                : Tenant::query()
                    ->where('application_id', $applicationId)
                    ->whereIn('plan', $matchingSlugs)
                    ->count();

            $featureCoverage[] = [
                'key' => $feature->key,
                'label' => $feature->label,
                'count' => $count,
                'percent' => $totalTenants > 0 ? round(($count / $totalTenants) * 100, 1) : 0.0,
            ];
        }

        $billing = $this->billingMetrics->metricsForApplication($applicationId);
        $mrrByPlan = collect($billing['plan_breakdown'] ?? [])->map(static function (array $row): array {
            return [
                'key' => $row['slug'],
                'label' => $row['name'],
                'mrr_cents' => (int) $row['mrr_cents'],
                'subscriptions' => (int) $row['subscriptions'],
            ];
        })->values()->all();

        return [
            'tenants_by_plan' => $tenantsByPlan,
            'tenants_by_status' => $tenantsByStatus,
            'feature_coverage' => $featureCoverage,
            'mrr_by_plan' => $mrrByPlan,
            'total_tenants' => $totalTenants,
        ];
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function drillDown(?int $applicationId, string $metric, string $value): Collection
    {
        if ($applicationId === null) {
            return new Collection;
        }

        $query = Tenant::query()
            ->where('application_id', $applicationId)
            ->orderBy('name');

        return match ($metric) {
            'plan', 'mrr' => $query->where('plan', $value)->get(),
            'status' => $query->where('status', $value)->get(),
            'feature' => $this->tenantsWithFeature($query, $applicationId, $value),
            default => throw ValidationException::withMessages([
                'metric' => 'Nepoznata metrika.',
            ]),
        };
    }

    public function metricLabel(string $metric): string
    {
        return match ($metric) {
            'plan' => 'Plan',
            'status' => 'Status',
            'feature' => 'Značajka',
            'mrr' => 'MRR po planu',
            default => $metric,
        };
    }

    public function valueLabel(?int $applicationId, string $metric, string $value): string
    {
        return match ($metric) {
            'plan', 'mrr' => SubscriptionPlan::query()
                ->where('application_id', $applicationId)
                ->where('slug', $value)
                ->value('name') ?? $value,
            'status' => TenantStatus::tryFrom($value)?->label() ?? $value,
            'feature' => ApplicationFeature::query()
                ->where('application_id', $applicationId)
                ->where('key', $value)
                ->value('label') ?? $value,
            default => $value,
        };
    }

    /**
     * @param  Builder<Tenant>  $query
     * @return Collection<int, Tenant>
     */
    private function tenantsWithFeature(Builder $query, int $applicationId, string $featureKey): Collection
    {
        $slugs = SubscriptionPlan::query()
            ->where('application_id', $applicationId)
            ->get()
            ->filter(fn (SubscriptionPlan $plan) => $plan->boolFeature($featureKey))
            ->pluck('slug')
            ->all();

        if ($slugs === []) {
            return new Collection;
        }

        return $query->whereIn('plan', $slugs)->get();
    }
}
