<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Identity\PlatformAuthService;
use App\Services\Identity\PlatformLoginRedirectService;
use App\Services\Identity\PlatformOAuthService;
use App\Support\PlatformLoginSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformLoginController extends Controller
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
        private readonly PlatformLoginRedirectService $loginRedirect,
        private readonly PlatformOAuthService $platformOAuth,
    ) {}

    public function create(Request $request): View
    {
        $applicationSlug = $request->string('application_slug')->toString();
        $application = $this->resolveApplication($applicationSlug);

        return view('auth.platform-login', [
            'applicationSlug' => $application?->slug ?? ($applicationSlug !== '' ? $applicationSlug : null),
            'applicationName' => $application?->name,
            'googleLoginUrl' => $this->platformOAuth->isGoogleConfigured()
                ? $this->oauthRedirectUrl($request, 'google')
                : null,
            'microsoftLoginUrl' => $this->platformOAuth->isMicrosoftConfigured()
                ? $this->oauthRedirectUrl($request, 'microsoft')
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $applicationSlug = $request->string('application_slug')->toString();

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        try {
            $user = $this->platformAuth->authenticateCredentials(
                $credentials['email'],
                $credentials['password'],
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->onlyInput('email');
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(PlatformLoginSession::PENDING_USER_ID, $user->id);
            $request->session()->put(
                PlatformLoginSession::PENDING_CHALLENGE_TOKEN,
                $this->platformAuth->createTwoFactorChallengeToken($user),
            );

            if ($applicationSlug !== '') {
                $request->session()->put(PlatformLoginSession::APPLICATION_SLUG, $applicationSlug);
            }

            return redirect()->route('platform.two-factor.login');
        }

        $auth = $this->platformAuth->issueAuthenticatedSession($user);

        return $this->loginRedirect->redirectAfterAuthentication(
            $user,
            $auth['token'],
            $applicationSlug !== '' ? $applicationSlug : null,
        );
    }

    public function continue(Request $request): RedirectResponse
    {
        $oauthError = $request->string('oauth_error')->toString();

        if ($oauthError !== '') {
            return redirect()
                ->route('platform.login', $this->loginRedirect->loginQuery(
                    $request->session()->get(PlatformLoginSession::APPLICATION_SLUG),
                ))
                ->withErrors(['email' => $oauthError]);
        }

        if ($request->boolean('two_factor_required') || $request->filled('two_factor_token')) {
            return redirect()->route('platform.two-factor.login', array_filter([
                'two_factor_token' => $request->string('two_factor_token')->toString() ?: null,
                'application_slug' => $request->session()->get(PlatformLoginSession::APPLICATION_SLUG),
            ]));
        }

        $token = $request->string('token')->toString();

        if ($token === '') {
            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Prijava nije uspjela. Pokušajte ponovno.']);
        }

        $user = $this->platformAuth->resolveUserFromPlainToken($token);

        if ($user === null) {
            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Prijava nije uspjela. Pokušajte ponovno.']);
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->platformAuth->logout($token);
            $request->session()->put(PlatformLoginSession::PENDING_USER_ID, $user->id);
            $request->session()->put(
                PlatformLoginSession::PENDING_CHALLENGE_TOKEN,
                $this->platformAuth->createTwoFactorChallengeToken($user),
            );

            return redirect()->route('platform.two-factor.login');
        }

        $applicationSlug = $request->session()->pull(PlatformLoginSession::APPLICATION_SLUG);

        return $this->loginRedirect->redirectAfterAuthentication(
            $user,
            $token,
            is_string($applicationSlug) && $applicationSlug !== '' ? $applicationSlug : null,
        );
    }

    public function pick(Request $request): View|RedirectResponse
    {
        $token = session(PlatformLoginSession::TOKEN);

        if (! is_string($token) || $token === '') {
            return redirect()->route('platform.login');
        }

        $user = $this->platformAuth->resolveUserFromPlainToken($token);

        if ($user === null) {
            session()->forget([PlatformLoginSession::TOKEN, PlatformLoginSession::APPLICATION_SLUG]);

            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Sesija je istekla. Prijavite se ponovno.']);
        }

        $applicationSlug = session(PlatformLoginSession::APPLICATION_SLUG);
        $workspaces = $this->loginRedirect->eligibleWorkspaces(
            $user,
            is_string($applicationSlug) && $applicationSlug !== '' ? $applicationSlug : null,
        );

        if ($workspaces === []) {
            session()->forget([PlatformLoginSession::TOKEN, PlatformLoginSession::APPLICATION_SLUG]);

            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Nemate pristup nijednoj organizaciji na platformi.']);
        }

        if (count($workspaces) === 1) {
            $handoffUrl = $this->loginRedirect->moduleHandoffUrl($token, $workspaces[0]);
            session()->forget([PlatformLoginSession::TOKEN, PlatformLoginSession::APPLICATION_SLUG]);

            if ($handoffUrl === null) {
                return redirect()
                    ->route('platform.login')
                    ->withErrors(['email' => 'Aplikacija nije konfigurirana za pristup.']);
            }

            return redirect()->away($handoffUrl);
        }

        $applications = Application::query()
            ->whereIn('slug', collect($workspaces)->pluck('application_slug')->filter()->unique()->all())
            ->get()
            ->keyBy('slug');

        return view('auth.platform-pick-workspace', [
            'workspaces' => $workspaces,
            'applications' => $applications,
        ]);
    }

    public function storePick(Request $request): RedirectResponse
    {
        $token = session(PlatformLoginSession::TOKEN);

        if (! is_string($token) || $token === '') {
            return redirect()->route('platform.login');
        }

        $validated = $request->validate([
            'workspace_key' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->platformAuth->resolveUserFromPlainToken($token);

        if ($user === null) {
            session()->forget([PlatformLoginSession::TOKEN, PlatformLoginSession::APPLICATION_SLUG]);

            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Sesija je istekla. Prijavite se ponovno.']);
        }

        $applicationSlug = session(PlatformLoginSession::APPLICATION_SLUG);
        $workspaces = $this->loginRedirect->eligibleWorkspaces(
            $user,
            is_string($applicationSlug) && $applicationSlug !== '' ? $applicationSlug : null,
        );

        $selected = collect($workspaces)->first(
            fn (array $workspace): bool => $this->workspaceKey($workspace) === $validated['workspace_key'],
        );

        if (! is_array($selected)) {
            return back()->withErrors([
                'workspace_key' => 'Odaberite valjanu organizaciju.',
            ]);
        }

        $handoffUrl = $this->loginRedirect->moduleHandoffUrl($token, $selected);
        session()->forget([PlatformLoginSession::TOKEN, PlatformLoginSession::APPLICATION_SLUG]);

        if ($handoffUrl === null) {
            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Aplikacija nije konfigurirana za pristup.']);
        }

        return redirect()->away($handoffUrl);
    }

    private function oauthRedirectUrl(Request $request, string $provider): string
    {
        $applicationSlug = $request->string('application_slug')->toString();
        $application = $this->resolveApplication($applicationSlug) ?? Application::query()->orderBy('id')->first();

        if ($application === null) {
            return '#';
        }

        $params = [
            'application_slug' => $application->slug,
            'return_url' => route('platform.login.continue', [], true),
            'platform_login' => 1,
        ];

        if ($applicationSlug !== '' && $applicationSlug !== $application->slug) {
            $params['application_slug'] = $applicationSlug;
        }

        return route('auth.'.$provider.'.redirect', $params, false);
    }

    private function resolveApplication(?string $applicationSlug): ?Application
    {
        if (! is_string($applicationSlug) || $applicationSlug === '') {
            return null;
        }

        return Application::query()->where('slug', $applicationSlug)->first();
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private function workspaceKey(array $workspace): string
    {
        return (string) ($workspace['application_slug'] ?? '').':'.(string) ($workspace['tenant']['external_id'] ?? '');
    }
}
