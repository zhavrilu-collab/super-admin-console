<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gdpr\AccountErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAccountErasureController extends Controller
{
    public function __construct(
        private readonly AccountErasureService $erasure,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Neautorizirano.');
        }

        if ($user->isSuperAdmin()) {
            abort(403, 'Super-admin računi se brišu kroz konzolu.');
        }

        return response()->json($this->erasure->statusPayload($user));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Neautorizirano.');
        }

        if ($user->isSuperAdmin()) {
            abort(403, 'Super-admin računi se brišu kroz konzolu.');
        }

        $deletionRequest = $this->erasure->requestDeletion($user, $request->ip());

        return response()->json([
            'message' => 'Zahtjev za brisanje računa je zaprimljen.',
            'deletion' => $this->erasure->statusPayload($user),
            'scheduled_deletion_at' => $deletionRequest->scheduled_deletion_at->toIso8601String(),
        ], 202);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Neautorizirano.');
        }

        if ($user->isSuperAdmin()) {
            abort(403, 'Super-admin računi se brišu kroz konzolu.');
        }

        $this->erasure->cancelDeletion($user);

        return response()->json([
            'message' => 'Zahtjev za brisanje računa je otkazan.',
            'deletion' => $this->erasure->statusPayload($user),
        ]);
    }
}
