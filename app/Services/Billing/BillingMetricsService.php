<?php

namespace App\Services\Billing;

use App\Enums\DunningResolution;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionDunningCase;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use Illuminate\Support\Collection;

class BillingMetricsService
{
    /**
     * @return array{
     *     currency: string,
     *     mrr_cents: int,
     *     arr_cents: int,
     *     active_subscriptions: int,
     *     priced_plans_missing: int,
     *     new_subscriptions_30d: int,
     *     churned_30d: int,
     *     churn_rate_percent: float,
     *     open_dunning_cases: int,
     *     estimated_ltv_cents: int,
     *     plan_breakdown: list<array{slug: string, name: string, subscriptions: int, mrr_cents: int}>,
     * }
     */
    public function metricsForApplication(?int $applicationId): array
    {
        if ($applicationId === null) {
            return $this->emptyMetrics();
        }

        $plans = SubscriptionPlan::query()
            ->where('application_id', $applicationId)
            ->orderBy('sort_order')
            ->get();

        $priceBySlug = $plans->mapWithKeys(
            fn (SubscriptionPlan $plan) => [$plan->slug => (int) ($plan->monthly_price_cents ?? 0)],
        );
        $priceByStripePrice = $plans
            ->filter(fn (SubscriptionPlan $plan) => filled($plan->stripe_price_id))
            ->mapWithKeys(fn (SubscriptionPlan $plan) => [$plan->stripe_price_id => (int) ($plan->monthly_price_cents ?? 0)]);

        $billableStatuses = [
            SubscriptionStatus::Active->value,
            SubscriptionStatus::Trialing->value,
        ];

        $subscriptions = TenantSubscription::query()
            ->with('tenant')
            ->whereIn('status', $billableStatuses)
            ->whereHas('tenant', fn ($query) => $query->where('application_id', $applicationId))
            ->get();

        $mrrCents = 0;
        $pricedPlansMissing = 0;
        $planCounts = [];

        foreach ($subscriptions as $subscription) {
            $priceCents = $this->resolvePriceCents($subscription, $priceBySlug, $priceByStripePrice);

            if ($priceCents <= 0) {
                $pricedPlansMissing++;

                continue;
            }

            $mrrCents += $priceCents;
            $slug = $subscription->tenant?->plan ?? 'unknown';
            $planCounts[$slug] = ($planCounts[$slug] ?? 0) + 1;
        }

        $planBreakdown = $plans->map(function (SubscriptionPlan $plan) use ($planCounts, $priceBySlug): array {
            $count = $planCounts[$plan->slug] ?? 0;
            $unitPrice = (int) ($priceBySlug[$plan->slug] ?? 0);

            return [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'subscriptions' => $count,
                'mrr_cents' => $count * $unitPrice,
            ];
        })->values()->all();

        $activeSubscriptions = $subscriptions->count();
        $newSubscriptions30d = TenantSubscription::query()
            ->whereIn('status', $billableStatuses)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('tenant', fn ($query) => $query->where('application_id', $applicationId))
            ->count();

        $churned30d = $this->churnedCountLast30Days($applicationId);
        $churnDenominator = max(1, $activeSubscriptions + $churned30d);
        $churnRate = round(($churned30d / $churnDenominator) * 100, 1);

        $arpuCents = $activeSubscriptions > 0 ? (int) round($mrrCents / $activeSubscriptions) : 0;
        $ltvMonths = (int) config('billing.metrics.default_ltv_months', 24);

        return [
            'currency' => strtoupper((string) config('billing.currency', 'eur')),
            'mrr_cents' => $mrrCents,
            'arr_cents' => $mrrCents * 12,
            'active_subscriptions' => $activeSubscriptions,
            'priced_plans_missing' => $pricedPlansMissing,
            'new_subscriptions_30d' => $newSubscriptions30d,
            'churned_30d' => $churned30d,
            'churn_rate_percent' => $churnRate,
            'open_dunning_cases' => SubscriptionDunningCase::query()
                ->whereNull('resolved_at')
                ->whereHas('tenant', fn ($query) => $query->where('application_id', $applicationId))
                ->count(),
            'estimated_ltv_cents' => $arpuCents * $ltvMonths,
            'plan_breakdown' => $planBreakdown,
        ];
    }

    public function formatMoney(int $cents, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?? (string) config('billing.currency', 'eur'));
        $amount = number_format($cents / 100, 2, ',', '.');

        return match ($currency) {
            'EUR' => $amount.' €',
            'USD' => '$'.$amount,
            default => $amount.' '.$currency,
        };
    }

    /**
     * @param  Collection<string, int>  $priceBySlug
     * @param  Collection<string, int>  $priceByStripePrice
     */
    private function resolvePriceCents(
        TenantSubscription $subscription,
        Collection $priceBySlug,
        Collection $priceByStripePrice,
    ): int {
        if (filled($subscription->stripe_price_id)) {
            $fromStripe = (int) ($priceByStripePrice[$subscription->stripe_price_id] ?? 0);

            if ($fromStripe > 0) {
                return $fromStripe;
            }
        }

        $tenantPlan = $subscription->tenant?->plan;

        if (is_string($tenantPlan) && $tenantPlan !== '') {
            return (int) ($priceBySlug[$tenantPlan] ?? 0);
        }

        return 0;
    }

    private function churnedCountLast30Days(int $applicationId): int
    {
        $since = now()->subDays(30);

        $canceledSubscriptions = TenantSubscription::query()
            ->where('status', SubscriptionStatus::Canceled->value)
            ->where('canceled_at', '>=', $since)
            ->whereHas('tenant', fn ($query) => $query->where('application_id', $applicationId))
            ->count();

        $dunningSuspended = SubscriptionDunningCase::query()
            ->where('resolution', DunningResolution::Suspended->value)
            ->where('resolved_at', '>=', $since)
            ->whereHas('tenant', fn ($query) => $query->where('application_id', $applicationId))
            ->count();

        return $canceledSubscriptions + $dunningSuspended;
    }

    /**
     * @return array{
     *     currency: string,
     *     mrr_cents: int,
     *     arr_cents: int,
     *     active_subscriptions: int,
     *     priced_plans_missing: int,
     *     new_subscriptions_30d: int,
     *     churned_30d: int,
     *     churn_rate_percent: float,
     *     open_dunning_cases: int,
     *     estimated_ltv_cents: int,
     *     plan_breakdown: list<array{slug: string, name: string, subscriptions: int, mrr_cents: int}>,
     * }
     */
    private function emptyMetrics(): array
    {
        return [
            'currency' => strtoupper((string) config('billing.currency', 'eur')),
            'mrr_cents' => 0,
            'arr_cents' => 0,
            'active_subscriptions' => 0,
            'priced_plans_missing' => 0,
            'new_subscriptions_30d' => 0,
            'churned_30d' => 0,
            'churn_rate_percent' => 0.0,
            'open_dunning_cases' => 0,
            'estimated_ltv_cents' => 0,
            'plan_breakdown' => [],
        ];
    }
}
