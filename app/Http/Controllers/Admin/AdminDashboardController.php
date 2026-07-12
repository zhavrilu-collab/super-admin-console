<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantListRequest;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Admin\TenantListService;
use App\Services\Admin\TenantSyncService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly TenantSyncService $tenantSyncService,
        private readonly TenantListService $tenantListService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}

    public function index(TenantListRequest $request): View
    {
        $activeApplication = $this->adminSaaSService->getActiveApplication();

        return view('admin.dashboard', [
            'applications' => $this->adminSaaSService->getAllApplications(),
            'activeApplication' => $activeApplication,
            'stats' => $this->adminSaaSService->getDashboardStats(),
            'subscriptionPlans' => $this->subscriptionPlanService->forApplication($activeApplication?->id),
            'tenants' => $this->tenantListService->paginateForApplication(
                $activeApplication?->id,
                $request,
            ),
            'filters' => $request,
            'syncSupported' => $activeApplication !== null
                && $this->tenantSyncService->isConfigured($activeApplication),
        ]);
    }
}
