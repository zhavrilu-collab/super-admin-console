<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OAuthProvider;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Identity\PlatformOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class PlatformOAuthController extends Controller
{
    public function __construct(
        private readonly PlatformOAuthService $platformOAuth,
    ) {}

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        return $this->redirectToProvider($request, OAuthProvider::Google);
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        return $this->handleProviderCallback($request, OAuthProvider::Google);
    }

    public function redirectToMicrosoft(Request $request): RedirectResponse
    {
        return $this->redirectToProvider($request, OAuthProvider::Microsoft);
    }

    public function handleMicrosoftCallback(Request $request): RedirectResponse
    {
        return $this->handleProviderCallback($request, OAuthProvider::Microsoft);
    }

    private function redirectToProvider(Request $request, OAuthProvider $provider): RedirectResponse
    {
        if (! $this->providerConfigured($provider)) {
            abort(503, $provider->label().' prijava nije konfigurirana.');
        }

        $validated = $request->validate([
            'application_slug' => ['required', 'string', 'max:100'],
            'return_url' => ['required', 'url', 'max:2048'],
        ]);

        $application = Application::query()
            ->where('slug', $validated['application_slug'])
            ->firstOrFail();

        $this->platformOAuth->validateReturnUrl($application, $validated['return_url']);

        $request->session()->put('platform_oauth.application_slug', $application->slug);
        $request->session()->put('platform_oauth.return_url', $validated['return_url']);
        $request->session()->put('platform_oauth.provider', $provider->value);

        return Socialite::driver($provider->value)->redirect();
    }

    private function handleProviderCallback(Request $request, OAuthProvider $provider): RedirectResponse
    {
        if (! $this->providerConfigured($provider)) {
            abort(503, $provider->label().' prijava nije konfigurirana.');
        }

        $returnUrl = $request->session()->pull('platform_oauth.return_url');
        $request->session()->forget(['platform_oauth.application_slug', 'platform_oauth.provider']);

        if (! is_string($returnUrl) || $returnUrl === '') {
            abort(400, 'OAuth sesija je istekla. Pokušajte ponovno.');
        }

        try {
            $oauthUser = Socialite::driver($provider->value)->user();
            $auth = match ($provider) {
                OAuthProvider::Google => $this->platformOAuth->loginFromGoogle($oauthUser),
                OAuthProvider::Microsoft => $this->platformOAuth->loginFromMicrosoft($oauthUser),
            };
        } catch (ValidationException $exception) {
            return $this->redirectWithError($returnUrl, (string) collect($exception->errors())->flatten()->first());
        } catch (\Throwable $exception) {
            Log::warning($provider->label().' OAuth callback failed.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectWithError($returnUrl, $provider->label().' prijava nije uspjela. Pokušajte ponovno.');
        }

        return redirect()->away($this->appendQuery($returnUrl, [
            'token' => $auth['token'],
        ]));
    }

    private function providerConfigured(OAuthProvider $provider): bool
    {
        return match ($provider) {
            OAuthProvider::Google => $this->platformOAuth->isGoogleConfigured(),
            OAuthProvider::Microsoft => $this->platformOAuth->isMicrosoftConfigured(),
        };
    }

    /**
     * @param  array<string, string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }

    private function redirectWithError(string $returnUrl, string $message): RedirectResponse
    {
        return redirect()->away($this->appendQuery($returnUrl, [
            'oauth_error' => $message,
        ]));
    }
}
