<?php

namespace App\Socialite;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class MicrosoftProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopes = ['openid', 'profile', 'email', 'User.Read'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->tenantEndpoint('authorize'), $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->tenantEndpoint('token');
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/me', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        /** @var array<string, mixed> $user */
        $user = json_decode((string) $response->getBody(), true);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $email = $user['mail'] ?? $user['userPrincipalName'] ?? null;

        return (new User)->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'nickname' => null,
            'name' => $user['displayName'] ?? null,
            'email' => is_string($email) ? $email : null,
            'avatar' => null,
        ]);
    }

    private function tenantEndpoint(string $path): string
    {
        $tenant = (string) config('services.microsoft.tenant', 'common');

        return 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/'.$path;
    }
}
