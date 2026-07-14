<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlatformLoginRequest;
use App\Services\Identity\PlatformAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAuthController extends Controller
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
    ) {}

    public function login(PlatformLoginRequest $request): JsonResponse
    {
        $payload = $this->platformAuth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json($payload);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Neautorizirano.');
        }

        return response()->json([
            'user' => $this->platformAuth->serializeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->platformAuth->logout($request->bearerToken());

        return response()->json(['message' => 'Odjava uspješna.']);
    }
}
