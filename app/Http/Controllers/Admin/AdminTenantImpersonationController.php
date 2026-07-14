<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartTenantImpersonationRequest;
use App\Models\Tenant;
use App\Services\Admin\AdminSaaSService;
use App\Services\Identity\PlatformImpersonationService;
use Illuminate\Http\RedirectResponse;

class AdminTenantImpersonationController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly PlatformImpersonationService $impersonation,
    ) {}

    public function store(StartTenantImpersonationRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant = $this->adminSaaSService->getTenantForActiveApp($tenant->id);
        $tenant->load('application');

        $started = $this->impersonation->start(
            $request->user(),
            $tenant,
            $request->string('reason')->toString() ?: null,
        );

        if ($started['redirect_url'] === null) {
            return redirect()
                ->back()
                ->with('warning', 'SaaS URL nije konfiguriran — nije moguće otvoriti impersonation.');
        }

        return redirect()->away($started['redirect_url']);
    }
}
