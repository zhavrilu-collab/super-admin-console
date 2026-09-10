<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateTenantStatusRequest;
use App\Http\Requests\Admin\ChangeTenantBillingPlanRequest;
use App\Http\Requests\Admin\StartTenantBillingCheckoutRequest;
use App\Http\Requests\Admin\UpdateTenantPlanRequest;
use App\Http\Requests\Admin\UpdateTenantSsoRequest;
use App\Http\Requests\Admin\UpdateTenantStatusRequest;
use App\Models\SubscriptionDunningCase;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\AuditLogService;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminTenantController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly AuditLogService $auditLogService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
        private readonly StripeBillingService $stripeBilling,
    ) {}

    public function show(Tenant $tenant): View
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);
        $tenant->load(['application', 'activeSubscription']);

        $openDunning = SubscriptionDunningCase::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('resolved_at')
            ->latest('started_at')
            ->first();

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'auditLogs' => $this->auditLogService->paginateForTenant($tenant),
            'saasUrl' => $this->saasUrlFor($tenant),
            'subscriptionPlans' => $this->subscriptionPlanService->forApplication($tenant->application_id),
            'activeSubscription' => $tenant->activeSubscription,
            'openDunning' => $openDunning,
            'stripeConfigured' => $this->stripeBilling->isConfigured(),
            'billablePlans' => $this->subscriptionPlanService
                ->forApplication($tenant->application_id)
                ->filter(static fn (SubscriptionPlan $plan) => filled($plan->stripe_price_id)),
        ]);
    }

    public function updateStatus(UpdateTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        try {
            $this->adminSaaSService->updateTenantStatus(
                $tenant->id,
                $request->validatedStatus(),
                $request->user(),
            );
        } catch (RuntimeException|ConnectionException $exception) {
            return redirect()
                ->back()
                ->with('warning', $exception instanceof ConnectionException
                    ? 'SaaS aplikacija ne odgovara. Provjerite API URL i mrežni pristup.'
                    : $exception->getMessage());
        }

        return redirect()
            ->back()
            ->with('status', 'Status tenanta je ažuriran.');
    }

    public function updatePlan(UpdateTenantPlanRequest $request, Tenant $tenant): RedirectResponse
    {
        try {
            $this->adminSaaSService->changeTenantPlan(
                $tenant->id,
                $request->validatedPlanSlug(),
                $request->user(),
            );
        } catch (RuntimeException|ConnectionException $exception) {
            return redirect()
                ->back()
                ->with('warning', $exception instanceof ConnectionException
                    ? 'SaaS aplikacija (udruga-saas) ne odgovara na portu 8000. Provjeri radi li server.'
                    : $exception->getMessage());
        }

        return redirect()
            ->back()
            ->with('status', 'Plan pretplate je ažuriran.');
    }

    public function updateSso(UpdateTenantSsoRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);
        $previous = (bool) $tenant->sso_enforced;
        $next = $request->ssoEnforced();

        if ($previous !== $next) {
            $tenant->forceFill(['sso_enforced' => $next])->save();
            $this->auditLogService->logTenantSsoChanged($request->user(), $tenant, $previous, $next);
        }

        return redirect()
            ->back()
            ->with('status', $next
                ? 'SSO prijava je sada obavezna za ovaj tenant.'
                : 'SSO prijava više nije obavezna za ovaj tenant.');
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        try {
            $this->adminSaaSService->deleteTenant($tenant->id, $request->user());
        } catch (RuntimeException|ConnectionException $exception) {
            return redirect()
                ->back()
                ->with('warning', $exception instanceof ConnectionException
                    ? 'SaaS aplikacija ne odgovara. Provjerite API URL i mrežni pristup.'
                    : $exception->getMessage());
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Tenant je obrisan.');
    }

    public function startBillingCheckout(StartTenantBillingCheckoutRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);

        if (! $this->stripeBilling->isConfigured()) {
            return redirect()->back()->with('warning', 'Stripe nije konfiguriran.');
        }

        $plan = SubscriptionPlan::query()
            ->where('application_id', $tenant->application_id)
            ->where('slug', $request->planSlug())
            ->firstOrFail();

        try {
            $session = $this->stripeBilling->createCheckoutSession(
                $tenant,
                $plan,
                route('admin.tenants.show', $tenant).'?billing=success',
                route('admin.tenants.show', $tenant).'?billing=cancel',
                $request->string('customer_email')->toString() ?: null,
            );
        } catch (\Throwable $exception) {
            return redirect()->back()->with('warning', $exception->getMessage());
        }

        if (! is_string($session['url'] ?? null) || $session['url'] === '') {
            return redirect()->back()->with('warning', 'Stripe Checkout nije vratio URL.');
        }

        return redirect()->away($session['url']);
    }

    public function openBillingPortal(Tenant $tenant): RedirectResponse
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);

        if (! $this->stripeBilling->isConfigured()) {
            return redirect()->back()->with('warning', 'Stripe nije konfiguriran.');
        }

        try {
            $session = $this->stripeBilling->createPortalSession(
                $tenant,
                route('admin.tenants.show', $tenant),
            );
        } catch (\Throwable $exception) {
            return redirect()->back()->with('warning', $exception->getMessage());
        }

        return redirect()->away($session['url']);
    }

    public function changeBillingPlan(ChangeTenantBillingPlanRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);

        if (! $this->stripeBilling->isConfigured()) {
            return redirect()->back()->with('warning', 'Stripe nije konfiguriran.');
        }

        $plan = SubscriptionPlan::query()
            ->where('application_id', $tenant->application_id)
            ->where('slug', $request->planSlug())
            ->firstOrFail();

        $previousPlan = $tenant->plan;

        try {
            $this->stripeBilling->changeSubscriptionPlan($tenant, $plan);
            $tenant->refresh();

            if ($tenant->plan !== $previousPlan) {
                $this->auditLogService->logTenantPlanChanged(
                    $request->user(),
                    $tenant,
                    $previousPlan,
                    $tenant->plan,
                );
            }
        } catch (\Throwable $exception) {
            return redirect()->back()->with('warning', $exception->getMessage());
        }

        return redirect()
            ->back()
            ->with('status', 'Stripe pretplata je ažurirana (s prorationom).');
    }

    public function bulkUpdateStatus(BulkUpdateTenantStatusRequest $request): RedirectResponse
    {
        $result = $this->adminSaaSService->bulkUpdateTenantStatus(
            $request->tenantIds(),
            $request->validatedStatus(),
            $request->user(),
        );

        $status = $request->validatedStatus();
        $message = match (true) {
            $result['updated'] === 0 && $result['skipped'] > 0 => 'Odabrani tenanti već imaju status '.$status->label().'.',
            $result['skipped'] > 0 => 'Ažurirano '.$result['updated'].' tenanata ('.$result['skipped'].' već je imalo taj status).',
            default => 'Ažurirano '.$result['updated'].' tenanata.',
        };

        return redirect()
            ->route('admin.dashboard', $request->redirectQuery())
            ->with('status', $message);
    }

    private function saasUrlFor(Tenant $tenant): ?string
    {
        $baseUrl = $tenant->application?->api_base_url;

        if (! is_string($baseUrl) || $baseUrl === '' || $tenant->slug === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/'.$tenant->slug;
    }
}
