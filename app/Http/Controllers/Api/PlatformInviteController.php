<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptPlatformInviteRequest;
use App\Http\Requests\Api\StorePlatformInviteRequest;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Identity\PlatformAuthService;
use App\Services\Identity\PlatformInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformInviteController extends Controller
{
    public function __construct(
        private readonly PlatformInviteService $invites,
        private readonly PlatformAuthService $platformAuth,
    ) {}

    public function store(StorePlatformInviteRequest $request): JsonResponse
    {
        $application = Application::query()
            ->where('slug', $request->string('application_slug')->toString())
            ->firstOrFail();

        $tenant = Tenant::query()
            ->where('application_id', $application->id)
            ->where('external_id', $request->string('tenant_external_id')->toString())
            ->firstOrFail();

        $invitedBy = null;
        $invitedByEmail = $request->string('invited_by_email')->toString();

        if ($invitedByEmail !== '') {
            $invitedBy = User::query()->where('email', $invitedByEmail)->first();
        }

        $issued = $this->invites->create(
            $application,
            $tenant,
            $request->string('email')->toString(),
            $request->string('role')->toString(),
            $invitedBy,
        );

        return response()->json([
            'invite' => $this->invites->serializeInvite($issued['invite']),
            'token' => $issued['plain_token'],
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $invite = \App\Models\PlatformInvite::findPendingByPlainToken($token);

        if ($invite === null) {
            abort(404, 'Pozivnica nije valjana ili je istekla.');
        }

        return response()->json([
            'invite' => $this->invites->serializeInvite($invite),
        ]);
    }

    public function accept(AcceptPlatformInviteRequest $request, string $token): JsonResponse
    {
        $result = $this->invites->accept(
            $token,
            $request->string('name')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['token'],
            'user' => $this->platformAuth->serializeUser($result['user']),
            'invite' => $this->invites->serializeInvite($result['invite']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $application = Application::query()
            ->where('slug', $request->string('application_slug')->toString())
            ->firstOrFail();

        $tenant = Tenant::query()
            ->where('application_id', $application->id)
            ->where('external_id', $request->string('tenant_external_id')->toString())
            ->firstOrFail();

        $invites = \App\Models\PlatformInvite::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($invite) => $this->invites->serializeInvite($invite))
            ->values();

        return response()->json(['invites' => $invites]);
    }
}
