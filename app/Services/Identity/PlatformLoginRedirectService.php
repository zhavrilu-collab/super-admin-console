<?php

namespace App\Services\Identity;

use App\Models\Application;
use App\Models\User;
use App\Support\PlatformLoginSession;
use Illuminate\Http\RedirectResponse;

class PlatformLoginRedirectService
{
    public function __construct(
        private readonly PlatformWorkspaceService $workspaces,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function eligibleWorkspaces(User $user, ?string $applicationSlug = null): array
    {
        $application = null;

        if (is_string($applicationSlug) && $applicationSlug !== '') {
            $application = Application::query()->where('slug', $applicationSlug)->first();
        }

        return array_values(array_filter(
            $this->workspaces->workspacesForUser($user, $application),
            fn (array $workspace): bool => $this->resolveApiBaseUrl($workspace) !== null,
        ));
    }

    public function redirectAfterAuthentication(User $user, string $token, ?string $applicationSlug = null): RedirectResponse
    {
        $eligible = $this->eligibleWorkspaces($user, $applicationSlug);

        if ($eligible === []) {
            return redirect()
                ->route('platform.login', $this->loginQuery($applicationSlug))
                ->withErrors([
                    'email' => 'Nemate pristup nijednoj organizaciji na platformi.',
                ]);
        }

        if (count($eligible) === 1) {
            $handoffUrl = $this->moduleHandoffUrl($token, $eligible[0]);

            if ($handoffUrl === null) {
                return redirect()
                    ->route('platform.login', $this->loginQuery($applicationSlug))
                    ->withErrors([
                        'email' => 'Aplikacija nije konfigurirana za pristup.',
                    ]);
            }

            return redirect()->away($handoffUrl);
        }

        session([
            PlatformLoginSession::TOKEN => $token,
            PlatformLoginSession::APPLICATION_SLUG => $applicationSlug,
        ]);

        return redirect()->route('platform.pick');
    }

    public function moduleHandoffUrl(string $token, array $workspace): ?string
    {
        $baseUrl = $this->resolveApiBaseUrl($workspace);

        if ($baseUrl === null) {
            return null;
        }

        return $baseUrl.'/auth/core/callback?'.http_build_query([
            'token' => $token,
        ]);
    }

    public function moduleCallbackUrl(Application $application): ?string
    {
        $baseUrl = $application->api_base_url;

        if (! is_string($baseUrl) || $baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/auth/core/callback';
    }

    /**
     * @return array<string, string>
     */
    public function loginQuery(?string $applicationSlug = null): array
    {
        if (! is_string($applicationSlug) || $applicationSlug === '') {
            return [];
        }

        return ['application_slug' => $applicationSlug];
    }

    private function resolveApiBaseUrl(array $workspace): ?string
    {
        $slug = $workspace['application_slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $application = Application::query()->where('slug', $slug)->first();
        $baseUrl = $application?->api_base_url;

        return is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
    }
}
