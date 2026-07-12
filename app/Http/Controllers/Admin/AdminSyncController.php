<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSaaSService;
use Illuminate\Http\RedirectResponse;

class AdminSyncController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
    ) {}

    public function pull(): RedirectResponse
    {
        $syncedCount = $this->adminSaaSService->syncActiveApplication();

        return redirect()
            ->back()
            ->with('status', "Sinkronizirano {$syncedCount} tenanata iz SaaS aplikacije.");
    }
}
