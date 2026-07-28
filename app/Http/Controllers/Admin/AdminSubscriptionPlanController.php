<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\ApplicationFeatureCatalogService;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminSubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
        private readonly ApplicationFeatureCatalogService $featureCatalog,
        private readonly StripeBillingService $stripeBilling,
    ) {}

    public function index(): View|RedirectResponse
    {
        $application = $this->adminSaaSService->getActiveApplication();

        if ($application === null) {
            return redirect()
                ->route('admin.applications.index')
                ->with('warning', 'Prvo odaberite ili kreirajte aplikaciju.');
        }

        $plans = $this->subscriptionPlanService->forApplication($application->id);
        $tenantCounts = $this->subscriptionPlanService->tenantCountsBySlug($application->id);

        return view('admin.subscription-plans.index', [
            'activeApplication' => $application,
            'plans' => $plans,
            'tenantCounts' => $tenantCounts,
            'badgeOptions' => $this->badgeOptions(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $application = $this->requireActiveApplication();
        $this->featureCatalog->seedDefaults($application);

        return view('admin.subscription-plans.create', [
            'activeApplication' => $application,
            'badgeOptions' => $this->badgeOptions(),
            'featureCatalog' => $this->featureCatalog->forApplication($application->id),
            'stripeConfigured' => $this->stripeBilling->isConfigured(),
        ]);
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $application = $this->requireActiveApplication();
        $payload = $request->validatedPayload();

        $plan = SubscriptionPlan::query()->create([
            'application_id' => $application->id,
            ...$payload,
        ]);

        if ($plan->is_default) {
            $this->subscriptionPlanService->setDefault($plan);
        }

        $status = 'Paket pretplate je dodan.';
        $warning = null;

        if ($request->shouldSyncStripeCatalog()) {
            $sync = $this->syncPlanToStripe($plan);
            $status = $sync['status'] ?? $status;
            $warning = $sync['warning'] ?? null;
        }

        $redirect = redirect()->route('admin.subscription-plans.index')->with('status', $status);

        return $warning !== null ? $redirect->with('warning', $warning) : $redirect;
    }

    public function edit(SubscriptionPlan $subscriptionPlan): View
    {
        $this->assertPlanBelongsToActiveApplication($subscriptionPlan);
        $application = $this->adminSaaSService->getActiveApplication();
        $this->featureCatalog->seedDefaults($application);

        return view('admin.subscription-plans.edit', [
            'activeApplication' => $application,
            'plan' => $subscriptionPlan,
            'badgeOptions' => $this->badgeOptions(),
            'featureCatalog' => $this->featureCatalog->forApplication($application?->id),
            'stripeConfigured' => $this->stripeBilling->isConfigured(),
        ]);
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->assertPlanBelongsToActiveApplication($subscriptionPlan);

        $previousSlug = $subscriptionPlan->slug;
        $payload = $request->validatedPayload();

        $subscriptionPlan->fill($payload);
        $subscriptionPlan->save();

        if ($previousSlug !== $subscriptionPlan->slug) {
            Tenant::query()
                ->where('application_id', $subscriptionPlan->application_id)
                ->where('plan', $previousSlug)
                ->update(['plan' => $subscriptionPlan->slug]);
        }

        if ($subscriptionPlan->is_default) {
            $this->subscriptionPlanService->setDefault($subscriptionPlan);
        }

        $status = 'Paket pretplate je ažuriran.';
        $warning = null;

        if ($request->shouldSyncStripeCatalog()) {
            $sync = $this->syncPlanToStripe($subscriptionPlan);
            $status = $sync['status'] ?? $status;
            $warning = $sync['warning'] ?? null;
        }

        $redirect = redirect()->route('admin.subscription-plans.index')->with('status', $status);

        return $warning !== null ? $redirect->with('warning', $warning) : $redirect;
    }

    public function syncStripe(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->assertPlanBelongsToActiveApplication($subscriptionPlan);

        $sync = $this->syncPlanToStripe($subscriptionPlan);
        $redirect = redirect()
            ->route('admin.subscription-plans.edit', $subscriptionPlan)
            ->with('status', $sync['status'] ?? 'Stripe katalog je sinkroniziran.');

        return isset($sync['warning'])
            ? $redirect->with('warning', $sync['warning'])
            : $redirect;
    }

    /**
     * @return array{status?: string, warning?: string}
     */
    private function syncPlanToStripe(SubscriptionPlan $plan): array
    {
        if (! $this->stripeBilling->isConfigured()) {
            return ['warning' => 'Stripe nije konfiguriran — paket je spremljen bez Stripe synca.'];
        }

        if ((int) ($plan->monthly_price_cents ?? 0) <= 0) {
            return ['warning' => 'Mjesečna cijena mora biti veća od 0 za automatski Stripe katalog.'];
        }

        try {
            $this->stripeBilling->ensureStripeCatalog($plan->fresh() ?? $plan);
        } catch (\Throwable $exception) {
            return ['warning' => 'Stripe sync nije uspio: '.$exception->getMessage()];
        }

        return ['status' => 'Paket je spremljen i sinkroniziran sa Stripe katalogom.'];
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->assertPlanBelongsToActiveApplication($subscriptionPlan);

        $tenantCount = Tenant::query()
            ->where('application_id', $subscriptionPlan->application_id)
            ->where('plan', $subscriptionPlan->slug)
            ->count();

        if ($tenantCount > 0) {
            return redirect()
                ->route('admin.subscription-plans.index')
                ->with('warning', 'Paket se ne može obrisati dok ga koristi '.$tenantCount.' tenanata.');
        }

        $wasDefault = $subscriptionPlan->is_default;
        $applicationId = $subscriptionPlan->application_id;
        $subscriptionPlan->delete();

        if ($wasDefault) {
            $replacement = SubscriptionPlan::query()
                ->where('application_id', $applicationId)
                ->orderBy('sort_order')
                ->first();

            if ($replacement !== null) {
                $this->subscriptionPlanService->setDefault($replacement);
            }
        }

        return redirect()
            ->route('admin.subscription-plans.index')
            ->with('status', 'Paket pretplate je obrisan.');
    }

    private function requireActiveApplication()
    {
        $application = $this->adminSaaSService->getActiveApplication();

        if ($application === null) {
            throw new NotFoundHttpException('Nema aktivne aplikacije.');
        }

        return $application;
    }

    private function assertPlanBelongsToActiveApplication(SubscriptionPlan $plan): void
    {
        $applicationId = $this->adminSaaSService->getActiveApplicationId();

        if ($applicationId === null || $plan->application_id !== $applicationId) {
            throw new NotFoundHttpException('Paket nije pronađen za aktivnu aplikaciju.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function badgeOptions(): array
    {
        return [
            'secondary' => 'Siva',
            'primary' => 'Plava',
            'dark' => 'Tamna',
            'success' => 'Zelena',
            'warning' => 'Žuta',
            'danger' => 'Crvena',
            'info' => 'Svijetlo plava',
        ];
    }
}
