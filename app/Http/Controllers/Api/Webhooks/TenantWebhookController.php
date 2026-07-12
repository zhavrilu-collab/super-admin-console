<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\TenantRegisteredWebhookRequest;
use App\Services\Webhooks\TenantWebhookService;
use Illuminate\Http\JsonResponse;

class TenantWebhookController extends Controller
{
    public function registered(
        TenantRegisteredWebhookRequest $request,
        TenantWebhookService $tenantWebhookService,
    ): JsonResponse {
        $tenant = $tenantWebhookService->handleTenantRegistered($request->validated());

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'external_id' => $tenant->external_id,
                'status' => $tenant->status->value,
                'plan' => $tenant->plan,
            ],
        ], $tenant->wasRecentlyCreated ? 201 : 200);
    }
}
