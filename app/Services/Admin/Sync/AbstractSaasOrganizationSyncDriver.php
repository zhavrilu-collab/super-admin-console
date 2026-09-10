<?php

namespace App\Services\Admin\Sync;

use App\Contracts\TenantSyncDriver;
use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractSaasOrganizationSyncDriver implements TenantSyncDriver
{
    abstract protected function applicationLabel(): string;

    abstract protected function devServerHint(): string;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pullTenants(Application $application): Collection
    {
        $config = $this->configFor($application);

        $response = $this->http($config['api_key'], $config['base_url'])
            ->get(rtrim($config['base_url'], '/').'/api/admin/organizations')
            ->throw();

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('data', []);

        return collect($items);
    }

    public function pushTenantStatus(Tenant $tenant, TenantStatus $status): void
    {
        $this->patchOrganization($tenant, [
            'status' => $status->value,
        ]);
    }

    public function pushTenantPlan(Tenant $tenant, string $planSlug): void
    {
        $tenant->loadMissing('activeSubscription');

        $payload = array_filter([
            'plan' => $planSlug,
            'stripe_customer_id' => $tenant->stripe_customer_id,
            'stripe_subscription_id' => $tenant->activeSubscription?->stripe_subscription_id,
        ], fn ($value) => $value !== null && $value !== '');

        $this->patchOrganization($tenant, $payload);
    }

    public function deleteTenant(Tenant $tenant): void
    {
        $tenant->loadMissing('application');

        if ($tenant->application === null) {
            throw new RuntimeException('Tenant nema povezanu aplikaciju za brisanje u SaaS aplikaciji.');
        }

        $config = $this->configFor($tenant->application);

        $externalId = is_string($tenant->external_id) && $tenant->external_id !== ''
            ? $tenant->external_id
            : $this->findOrganizationIdBySlug($tenant, $config);

        if ($externalId === null || $externalId === '') {
            return;
        }

        try {
            $this->sendDelete($config, $externalId);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                return;
            }

            throw new RuntimeException(
                $this->syncFailureMessage($tenant, $exception),
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function patchOrganization(Tenant $tenant, array $payload): void
    {
        $config = $this->configFor($tenant->application);
        $externalId = $this->resolveExternalId($tenant, $config);

        try {
            $this->sendPatch($config, $externalId, $payload);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                $resolvedId = $this->findOrganizationIdBySlug($tenant, $config);

                if ($resolvedId !== null) {
                    $this->assignExternalId($tenant, $resolvedId);
                    $this->sendPatch($config, $resolvedId, $payload);

                    return;
                }
            }

            throw new RuntimeException(
                $this->syncFailureMessage($tenant, $exception),
                previous: $exception,
            );
        }
    }

    /**
     * @param  array{base_url: string, api_key: string}  $config
     * @param  array<string, string>  $payload
     */
    private function sendPatch(array $config, string $externalId, array $payload): void
    {
        $this->http($config['api_key'], $config['base_url'])
            ->patch(
                rtrim($config['base_url'], '/').'/api/admin/organizations/'.$externalId,
                $payload,
            )
            ->throw();
    }

    /**
     * @param  array{base_url: string, api_key: string}  $config
     */
    private function sendDelete(array $config, string $externalId): void
    {
        $this->http($config['api_key'], $config['base_url'])
            ->delete(rtrim($config['base_url'], '/').'/api/admin/organizations/'.$externalId)
            ->throw();
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function http(string $apiKey, ?string $baseUrl = null)
    {
        $request = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5);

        if (! config('saas_applications.http_verify', true)) {
            $request = $request->withoutVerifying();
        }

        if (config('saas_applications.http_resolve_loopback', false) && is_string($baseUrl) && $baseUrl !== '') {
            $host = parse_url($baseUrl, PHP_URL_HOST);
            $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'http://') ? 80 : 443);

            if (is_string($host) && $host !== '' && $host !== '127.0.0.1' && $host !== 'localhost') {
                $request = $request->withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => [sprintf('%s:%d:127.0.0.1', $host, (int) $port)],
                    ],
                ]);
            }
        }

        return $request;
    }

    /**
     * @param  array{base_url: string, api_key: string}  $config
     */
    private function resolveExternalId(Tenant $tenant, array $config): string
    {
        $externalId = $tenant->external_id;

        if (is_string($externalId) && $externalId !== '') {
            return $externalId;
        }

        $resolvedId = $this->findOrganizationIdBySlug($tenant, $config);

        if ($resolvedId === null) {
            throw new RuntimeException(
                'Tenant nema ispravan external_id za sinkronizaciju. Pokreni sinkronizaciju tenanata.',
            );
        }

        $this->assignExternalId($tenant, $resolvedId);

        return $resolvedId;
    }

    private function assignExternalId(Tenant $tenant, string $resolvedId): void
    {
        if ($tenant->external_id === $resolvedId) {
            return;
        }

        $conflict = Tenant::query()
            ->where('application_id', $tenant->application_id)
            ->where('external_id', $resolvedId)
            ->where('id', '!=', $tenant->id)
            ->first();

        if ($conflict !== null) {
            $conflict->external_id = null;
            $conflict->save();
        }

        $tenant->external_id = $resolvedId;
        $tenant->save();
    }

    /**
     * @param  array{base_url: string, api_key: string}  $config
     */
    private function findOrganizationIdBySlug(Tenant $tenant, array $config): ?string
    {
        if ($tenant->slug === '') {
            return null;
        }

        try {
            $response = $this->http($config['api_key'], $config['base_url'])
                ->get(rtrim($config['base_url'], '/').'/api/admin/organizations')
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Dohvat organizacija iz '.$this->applicationLabel().' nije uspio: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('data', []);

        foreach ($items as $item) {
            if (($item['slug'] ?? null) === $tenant->slug) {
                return (string) ($item['id'] ?? '');
            }
        }

        return null;
    }

    private function syncFailureMessage(Tenant $tenant, RequestException $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'SaaS aplikacija ('.$this->applicationLabel().') ne odgovara. '.$this->devServerHint();
        }

        $status = $exception->response?->status();

        if ($status === 404) {
            return 'Organizacija "'.$tenant->name.'" nije pronađena u '.$this->applicationLabel().'. Pokreni sinkronizaciju tenanata pa pokušaj ponovno.';
        }

        if ($status === 422) {
            return $this->applicationLabel().' je odbila promjenu paketa. Provjeri da su paketi pretplate sinkronizirani.';
        }

        return 'Sinkronizacija prema '.$this->applicationLabel().' nije uspjela: '.$exception->getMessage();
    }

    /**
     * @return array{base_url: string, api_key: string}
     */
    private function configFor(Application $application): array
    {
        $baseUrl = $application->api_base_url;
        $apiKey = $application->api_sync_key;

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($apiKey) || $apiKey === '') {
            $config = config('saas_applications.applications.'.$application->slug);

            if (! is_array($config)) {
                throw new RuntimeException('Nema konfiguracije sinkronizacije za aplikaciju: '.$application->slug);
            }

            $baseUrl = $config['base_url'] ?? null;
            $apiKey = $config['api_key'] ?? null;
        }

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('API URL ili ključ nisu konfigurirani za: '.$application->slug);
        }

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
        ];
    }
}
