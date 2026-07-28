<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application' => ['required', 'string', 'max:100'],
        ]);

        $application = Application::query()
            ->where('slug', $validated['application'])
            ->firstOrFail();

        $plans = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static fn (SubscriptionPlan $plan): array => $plan->toSyncArray())
            ->values();

        return response()->json([
            'data' => $plans,
            'meta' => [
                'application' => $application->slug,
                'total' => $plans->count(),
            ],
        ]);
    }
}
