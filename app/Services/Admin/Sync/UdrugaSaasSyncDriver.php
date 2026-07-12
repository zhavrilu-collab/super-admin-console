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

class UdrugaSaasSyncDriver implements TenantSyncDriver
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pullTenants(Application $application): Collection
    {
        $config = $this->configFor($application);

        $response = Http::withToken($config['api_key'])
            ->acceptJson()
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
        $this->patchOrganization($tenant, [
            'plan' => $planSlug,
        ]);
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
        Http::withToken($config['api_key'])
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->patch(
                rtrim($config['base_url'], '/').'/api/admin/organizations/'.$externalId,
                $payload,
            )
            ->throw();
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
            $response = Http::withToken($config['api_key'])
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get(rtrim($config['base_url'], '/').'/api/admin/organizations')
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Dohvat organizacija iz udruga-saas nije uspio: '.$exception->getMessage(),
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
            return 'SaaS aplikacija (udruga-saas) ne odgovara. Provjeri radi li server na http://127.0.0.1:8000.';
        }

        $status = $exception->response?->status();

        if ($status === 404) {
            return 'Organizacija "'.$tenant->name.'" nije pronađena u SaaS aplikaciji. Pokreni sinkronizaciju tenanata pa pokušaj ponovno.';
        }

        if ($status === 422) {
            return 'SaaS aplikacija je odbila promjenu paketa. Provjeri da su paketi pretplate sinkronizirani.';
        }

        return 'Sinkronizacija prema udruga-saas nije uspjela: '.$exception->getMessage();
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
