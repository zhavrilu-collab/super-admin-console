<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateTenantStatusRequest;
use App\Http\Requests\Admin\UpdateTenantPlanRequest;
use App\Http\Requests\Admin\UpdateTenantStatusRequest;
use App\Models\Tenant;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\AuditLogService;
use App\Services\Admin\SubscriptionPlanService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class AdminTenantController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly AuditLogService $auditLogService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}

    public function show(Tenant $tenant): View
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);
        $tenant->load('application');

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'auditLogs' => $this->auditLogService->paginateForTenant($tenant),
            'saasUrl' => $this->saasUrlFor($tenant),
            'subscriptionPlans' => $this->subscriptionPlanService->forApplication($tenant->application_id),
        ]);
    }

    public function updateStatus(UpdateTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->adminSaaSService->updateTenantStatus(
            $tenant->id,
            $request->validatedStatus(),
            $request->user(),
        );

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
