<?php

namespace App\Http\Controllers\Auth;

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
        if (! $this->platformOAuth->isGoogleConfigured()) {
            abort(503, 'Google prijava nije konfigurirana.');
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

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if (! $this->platformOAuth->isGoogleConfigured()) {
            abort(503, 'Google prijava nije konfigurirana.');
        }

        $returnUrl = $request->session()->pull('platform_oauth.return_url');
        $request->session()->forget('platform_oauth.application_slug');

        if (! is_string($returnUrl) || $returnUrl === '') {
            abort(400, 'OAuth sesija je istekla. Pokušajte ponovno.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $auth = $this->platformOAuth->loginFromGoogle($googleUser);
        } catch (ValidationException $exception) {
            return $this->redirectWithError($returnUrl, (string) collect($exception->errors())->flatten()->first());
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectWithError($returnUrl, 'Google prijava nije uspjela. Pokušajte ponovno.');
        }

        return redirect()->away($this->appendQuery($returnUrl, [
            'token' => $auth['token'],
        ]));
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
