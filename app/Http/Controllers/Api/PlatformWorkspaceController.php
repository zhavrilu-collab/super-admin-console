<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncPlatformMembershipsRequest;
use App\Models\Application;
use App\Services\Identity\PlatformWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformWorkspaceController extends Controller
{
    public function __construct(
        private readonly PlatformWorkspaceService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Neautorizirano.');
        }

        $application = null;
        $applicationSlug = $request->string('application_slug')->toString();

        if ($applicationSlug !== '') {
            $application = Application::query()
                ->where('slug', $applicationSlug)
                ->firstOrFail();
        }

        return response()->json([
            'workspaces' => $this->workspaces->workspacesForUser($user, $application),
        ]);
    }

    public function sync(SyncPlatformMembershipsRequest $request): JsonResponse
    {
        $application = Application::query()
            ->where('slug', $request->string('application_slug')->toString())
            ->firstOrFail();

        $synced = $this->workspaces->syncMemberships(
            $application,
            $request->input('memberships', []),
        );

        return response()->json([
            'synced' => $synced,
        ]);
    }
}
