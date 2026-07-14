<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        StripeBillingService $billing,
        StripeWebhookService $webhooks,
    ): JsonResponse {
        if (! $billing->isConfigured()) {
            abort(503, 'Stripe nije konfiguriran.');
        }

        if ($billing->webhookSecret() === null || $billing->webhookSecret() === '') {
            abort(503, 'Stripe webhook secret nije konfiguriran.');
        }

        try {
            $event = $webhooks->constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            abort(400, 'Neispravan Stripe webhook.');
        }

        $webhooks->handle($event);

        return response()->json(['received' => true]);
    }
}
