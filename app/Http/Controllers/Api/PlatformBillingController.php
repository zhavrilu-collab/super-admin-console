<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateBillingCheckoutRequest;
use App\Http\Requests\Api\CreateBillingPortalRequest;
use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Admin\TenantSyncService;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlatformBillingController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $billing,
        private readonly TenantSyncService $tenantSync,
    ) {}

    public function checkout(CreateBillingCheckoutRequest $request): JsonResponse
    {
        if (! $this->billing->isConfigured()) {
            abort(503, 'Stripe nije konfiguriran.');
        }

        $application = Application::query()
            ->where('slug', $request->string('application_slug')->toString())
            ->firstOrFail();

        $tenant = Tenant::query()
            ->where('application_id', $application->id)
            ->where('external_id', $request->string('tenant_external_id')->toString())
            ->firstOrFail();

        $plan = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', $request->string('plan_slug')->toString())
            ->firstOrFail();

        if ($tenant->plan === $plan->slug) {
            return response()->json([
                'message' => 'Tenant već koristi ovaj paket.',
            ], 422);
        }

        $tenant->loadMissing('activeSubscription');
        $successUrl = $request->string('success_url')->toString();
        $cancelUrl = $request->string('cancel_url')->toString();

        if ($tenant->activeSubscription !== null && $tenant->activeSubscription->isActive()) {
            $this->billing->changeSubscriptionPlan($tenant, $plan);
            $tenant->refresh();

            try {
                $this->tenantSync->pushPlan($tenant, $tenant->plan);
            } catch (\Throwable $exception) {
                Log::warning('Billing plan change sync to SaaS failed.', [
                    'tenant_id' => $tenant->id,
                    'plan' => $tenant->plan,
                    'message' => $exception->getMessage(),
                ]);
            }

            return response()->json([
                'data' => [
                    'mode' => 'updated',
                    'redirect_url' => $successUrl,
                    'plan' => $tenant->plan,
                ],
            ]);
        }

        $session = $this->billing->createCheckoutSession(
            $tenant,
            $plan,
            $successUrl,
            $cancelUrl,
            $request->string('customer_email')->toString() ?: null,
        );

        return response()->json([
            'data' => array_merge($session, [
                'mode' => 'checkout',
            ]),
        ]);
    }

    public function portal(CreateBillingPortalRequest $request): JsonResponse
    {
        if (! $this->billing->isConfigured()) {
            abort(503, 'Stripe nije konfiguriran.');
        }

        $application = Application::query()
            ->where('slug', $request->string('application_slug')->toString())
            ->firstOrFail();

        $tenant = Tenant::query()
            ->where('application_id', $application->id)
            ->where('external_id', $request->string('tenant_external_id')->toString())
            ->firstOrFail();

        $session = $this->billing->createPortalSession(
            $tenant,
            $request->string('return_url')->toString(),
        );

        return response()->json([
            'data' => $session,
        ]);
    }
}
