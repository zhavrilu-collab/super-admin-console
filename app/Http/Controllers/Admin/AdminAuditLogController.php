<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\AuditLogService;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(): View
    {
        $activeApplication = $this->adminSaaSService->getActiveApplication();

        return view('admin.audit.index', [
            'activeApplication' => $activeApplication,
            'auditLogs' => $this->auditLogService->paginateForApplication(
                $activeApplication?->id,
            ),
        ]);
    }
}
