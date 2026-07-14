<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogService;
use App\Services\Identity\PlatformImpersonationService;
use Illuminate\Http\JsonResponse;

class PlatformImpersonationController extends Controller
{
    public function __construct(
        private readonly PlatformImpersonationService $impersonation,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function show(string $token): JsonResponse
    {
        $session = \App\Models\ImpersonationSession::findActiveByPlainToken($token);

        if ($session === null) {
            abort(404, 'Impersonation sesija nije valjana ili je istekla.');
        }

        $session = $this->impersonation->markStarted($session);
        $this->auditLogService->logImpersonationStarted($session);

        return response()->json([
            'session' => $this->impersonation->serializeSession($session),
        ]);
    }

    public function end(string $token): JsonResponse
    {
        $session = $this->impersonation->end($token);
        $this->auditLogService->logImpersonationEnded($session);

        return response()->json([
            'message' => 'Impersonation sesija je završena.',
        ]);
    }
}
