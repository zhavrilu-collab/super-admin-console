<?php

namespace App\Services\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionPlanService
{
    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function forApplication(?int $applicationId): Collection
    {
        if ($applicationId === null) {
            return new Collection();
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

    public function seedDefaults(Application $application): void
    {
        $defaults = [
            [
                'slug' => 'basic',
                'name' => 'Osnovni',
                'member_limit' => 50,
                'badge_class' => 'secondary',
                'sort_order' => 1,
                'is_default' => true,
                'subdomain' => false,
                'custom_domain' => false,
                'editable_sections' => false,
                'cookie_banner' => false,
            ],
            [
                'slug' => 'standard',
                'name' => 'Standardni',
                'member_limit' => 500,
                'badge_class' => 'primary',
                'sort_order' => 2,
                'is_default' => false,
                'subdomain' => true,
                'custom_domain' => false,
                'editable_sections' => true,
                'cookie_banner' => true,
            ],
            [
                'slug' => 'premium',
                'name' => 'Napredni',
                'member_limit' => null,
                'badge_class' => 'dark',
                'sort_order' => 3,
                'is_default' => false,
                'subdomain' => true,
                'custom_domain' => true,
                'editable_sections' => true,
                'cookie_banner' => true,
            ],
        ];

        foreach ($defaults as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                [
                    'application_id' => $application->id,
                    'slug' => $plan['slug'],
                ],
                $plan,
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
