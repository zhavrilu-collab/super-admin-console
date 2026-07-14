<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSaaSService;
use App\Services\Billing\BillingMetricsService;
use Illuminate\View\View;

class AdminBillingDashboardController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly BillingMetricsService $billingMetrics,
    ) {}

    public function index(): View
    {
        $activeApplication = $this->adminSaaSService->getActiveApplication();
        $metrics = $this->billingMetrics->metricsForApplication($activeApplication?->id);

        return view('admin.billing.index', [
            'applications' => $this->adminSaaSService->getAllApplications(),
            'activeApplication' => $activeApplication,
            'metrics' => $metrics,
            'billingMetrics' => $this->billingMetrics,
        ]);
    }
}
