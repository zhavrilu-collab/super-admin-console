<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PlatformTwoFactorChallengeRequest;
use App\Models\User;
use App\Services\Identity\PlatformAuthService;
use App\Services\Identity\PlatformLoginRedirectService;
use App\Support\PlatformLoginSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformTwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
        private readonly PlatformLoginRedirectService $loginRedirect,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->filled('two_factor_token')) {
            $challengeToken = $request->string('two_factor_token')->toString();
            $user = $this->platformAuth->resolveTwoFactorChallengeUser($challengeToken);

            if ($user === null) {
                return redirect()
                    ->route('platform.login')
                    ->withErrors(['email' => '2FA sesija je istekla. Prijavite se ponovno.']);
            }

            $request->session()->put(PlatformLoginSession::PENDING_CHALLENGE_TOKEN, $challengeToken);
            $request->session()->put(PlatformLoginSession::PENDING_USER_ID, $user->id);

            if ($request->filled('return_url')) {
                $returnUrl = $request->string('return_url')->toString();
                $applicationSlug = $request->string('application_slug')->toString();

                if ($applicationSlug !== '') {
                    $application = \App\Models\Application::query()->where('slug', $applicationSlug)->first();

                    if ($application !== null) {
                        try {
                            app(\App\Services\Identity\PlatformOAuthService::class)
                                ->validateReturnUrl($application, $returnUrl);
                        } catch (\Illuminate\Validation\ValidationException $exception) {
                            return redirect()
                                ->route('platform.login')
                                ->withErrors(['email' => (string) collect($exception->errors())->flatten()->first()]);
                        }
                    }
                }

                $request->session()->put(PlatformLoginSession::PENDING_RETURN_URL, $returnUrl);
            }

            if ($request->filled('application_slug')) {
                $request->session()->put(
                    PlatformLoginSession::APPLICATION_SLUG,
                    $request->string('application_slug')->toString(),
                );
            }
        }

        if (! $this->hasPendingChallenge($request)) {
            return redirect()->route('platform.login');
        }

        return view('auth.platform-two-factor-challenge');
    }

    public function store(PlatformTwoFactorChallengeRequest $request): RedirectResponse
    {
        $challengeToken = $request->session()->get(PlatformLoginSession::PENDING_CHALLENGE_TOKEN);
        $pendingUserId = $request->session()->get(PlatformLoginSession::PENDING_USER_ID);

        if (! is_string($challengeToken) || $challengeToken === '') {
            if (! is_int($pendingUserId) && ! is_numeric($pendingUserId)) {
                return redirect()->route('platform.login');
            }

            $user = User::query()->find((int) $pendingUserId);

            if ($user === null || ! $user->hasTwoFactorEnabled()) {
                return redirect()->route('platform.login');
            }

            $challengeToken = $this->platformAuth->createTwoFactorChallengeToken($user);
        }

        try {
            $auth = $this->platformAuth->completeTwoFactorChallenge(
                $challengeToken,
                $request->filled('code') ? $request->string('code')->toString() : null,
                $request->filled('recovery_code') ? $request->string('recovery_code')->toString() : null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $returnUrl = $request->session()->pull(PlatformLoginSession::PENDING_RETURN_URL);
        $applicationSlug = $request->session()->pull(PlatformLoginSession::APPLICATION_SLUG);

        $request->session()->forget([
            PlatformLoginSession::PENDING_USER_ID,
            PlatformLoginSession::PENDING_CHALLENGE_TOKEN,
        ]);

        $user = $this->platformAuth->resolveUserFromPlainToken($auth['token']);

        if ($user === null) {
            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Prijava nije uspjela. Pokušajte ponovno.']);
        }

        if (is_string($returnUrl) && $returnUrl !== '') {
            $separator = str_contains($returnUrl, '?') ? '&' : '?';

            return redirect()->away($returnUrl.$separator.http_build_query([
                'token' => $auth['token'],
            ]));
        }

        return $this->loginRedirect->redirectAfterAuthentication(
            $user,
            $auth['token'],
            is_string($applicationSlug) && $applicationSlug !== '' ? $applicationSlug : null,
        );
    }

    private function hasPendingChallenge(Request $request): bool
    {
        return $request->session()->has(PlatformLoginSession::PENDING_USER_ID)
            || $request->session()->has(PlatformLoginSession::PENDING_CHALLENGE_TOKEN);
    }
}
