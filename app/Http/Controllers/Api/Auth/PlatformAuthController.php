<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlatformLoginRequest;
use App\Http\Requests\Api\PlatformRegisterRequest;
use App\Http\Requests\Api\PlatformTwoFactorChallengeApiRequest;
use App\Http\Requests\Api\PlatformTwoFactorConfirmRequest;
use App\Http\Requests\Api\PlatformTwoFactorDisableRequest;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Services\Identity\PlatformAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PlatformAuthController extends Controller
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function login(PlatformLoginRequest $request): JsonResponse
    {
        $payload = $this->platformAuth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if (($payload['two_factor_required'] ?? false) === true) {
            return response()->json([
                'two_factor_required' => true,
                'two_factor_token' => $payload['two_factor_token'],
                'challenge_url' => url('/platform/prijava/2fa'),
            ]);
        }

        return response()->json($payload);
    }

    public function completeTwoFactor(PlatformTwoFactorChallengeApiRequest $request): JsonResponse
    {
        $payload = $this->platformAuth->completeTwoFactorChallenge(
            $request->string('two_factor_token')->toString(),
            $request->filled('code') ? $request->string('code')->toString() : null,
            $request->filled('recovery_code') ? $request->string('recovery_code')->toString() : null,
        );

        return response()->json($payload);
    }

    public function register(PlatformRegisterRequest $request): JsonResponse
    {
        $payload = $this->platformAuth->register(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json($payload, 201);
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

    public function beginTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformUser()) {
            abort(401, 'Neautorizirano.');
        }

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['2FA je već uključena.'],
            ]);
        }

        $secret = $this->twoFactor->generateSecretKey();

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->twoFactor->qrCodeSvg($user, $secret),
        ]);
    }

    public function confirmTwoFactor(PlatformTwoFactorConfirmRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformUser()) {
            abort(401, 'Neautorizirano.');
        }

        $secret = $request->string('secret')->toString();

        if ($secret === '') {
            throw ValidationException::withMessages([
                'secret' => ['Prvo pokreni postavljanje 2FA.'],
            ]);
        }

        try {
            $recoveryCodes = $this->twoFactor->enable(
                $user,
                $secret,
                $request->string('code')->toString(),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'code' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'message' => '2FA je uključena.',
            'recovery_codes' => $recoveryCodes,
            'user' => $this->platformAuth->serializeUser($user->fresh()),
        ]);
    }

    public function disableTwoFactor(PlatformTwoFactorDisableRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformUser()) {
            abort(401, 'Neautorizirano.');
        }

        try {
            $this->twoFactor->disable(
                $user,
                $request->string('password')->toString(),
                $request->string('code')->toString(),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'code' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'message' => '2FA je isključena.',
            'user' => $this->platformAuth->serializeUser($user->fresh()),
        ]);
    }
}
