<?php

namespace App\Services\Admin;

use App\Contracts\TenantSyncDriver;
use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Admin\Sync\UdrugaSaasSyncDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TenantSyncService
{
    public function __construct(
        private readonly SuperAdminNotificationService $superAdminNotificationService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}
    public function resolveDriver(Application $application): TenantSyncDriver
    {
        $driverClass = $this->resolveDriverClass($application);

        if ($driverClass === null) {
            throw new RuntimeException('Sinkronizacija nije podržana za aplikaciju: '.$application->slug);
        }

        return app($driverClass);
    }

    public function supports(Application $application): bool
    {
        return $this->resolveDriverClass($application) !== null;
    }

    public function isConfigured(Application $application): bool
    {
        if (! $this->supports($application)) {
            return false;
        }

        if (is_string($application->api_base_url) && $application->api_base_url !== ''
            && is_string($application->api_sync_key) && $application->api_sync_key !== '') {
            return true;
        }

        $config = config('saas_applications.applications.'.$application->slug);

        if (! is_array($config)) {
            return false;
        }

        $baseUrl = $config['base_url'] ?? null;
        $apiKey = $config['api_key'] ?? null;

        return is_string($baseUrl) && $baseUrl !== ''
            && is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @return class-string<TenantSyncDriver>|null
     */
    private function resolveDriverClass(Application $application): ?string
    {
        $candidates = [];

        if (is_string($application->sync_driver) && $application->sync_driver !== '') {
            $candidates[] = $application->sync_driver;

            $drivers = config('saas_applications.drivers', []);
            if (is_array($drivers) && isset($drivers[$application->sync_driver]['class'])) {
                $candidates[] = $drivers[$application->sync_driver]['class'];
            }
        }

        $config = config('saas_applications.applications.'.$application->slug);
        if (is_array($config) && isset($config['driver'])) {
            $candidates[] = $config['driver'];
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (is_subclass_of($candidate, TenantSyncDriver::class)) {
                return $candidate;
            }
        }

        return null;
    }

    public function pullForApplication(Application $application): int
    {
        $driver = $this->resolveDriver($application);
        $remoteTenants = $driver->pullTenants($application);
        $syncedCount = 0;

        foreach ($remoteTenants as $remoteTenant) {
            $this->upsertTenant($application, $remoteTenant);
            $syncedCount++;
        }

        $application->forceFill(['last_synced_at' => now()])->save();

        return $syncedCount;
    }

    /**
     * @return array{
     *     synced_tenants: int,
     *     synced_applications: int,
     *     skipped_applications: int,
     *     failed_applications: int,
     *     results: array<string, array{status: string, tenants?: int, message?: string}>
     * }
     */
    public function pullAllConfigured(?string $applicationSlug = null): array
    {
        $query = Application::query()->orderBy('name');

        if ($applicationSlug !== null) {
            $query->where('slug', $applicationSlug);
        }

        $syncedTenants = 0;
        $syncedApplications = 0;
        $skippedApplications = 0;
        $failedApplications = 0;
        $results = [];

        foreach ($query->get() as $application) {
            if (! $this->isConfigured($application)) {
                $skippedApplications++;
                $results[$application->slug] = [
                    'status' => 'skipped',
                    'message' => 'API nije konfiguriran',
                ];

                continue;
            }

            try {
                $count = $this->pullForApplication($application);
                $syncedTenants += $count;
                $syncedApplications++;
                $results[$application->slug] = [
                    'status' => 'synced',
                    'tenants' => $count,
                ];
            } catch (\Throwable $exception) {
                $failedApplications++;
                $results[$application->slug] = [
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ];

                Log::warning('Tenant sync failed for application.', [
                    'application_id' => $application->id,
                    'application_slug' => $application->slug,
                    'exception' => $exception,
                ]);
            }
        }

        return [
            'synced_tenants' => $syncedTenants,
            'synced_applications' => $syncedApplications,
            'skipped_applications' => $skippedApplications,
            'failed_applications' => $failedApplications,
            'results' => $results,
        ];
    }

    public function pushStatus(Tenant $tenant, TenantStatus $status): void
    {
        $tenant->loadMissing('application');

        if ($tenant->application === null) {
            throw new RuntimeException('Tenant nema povezanu aplikaciju za sinkronizaciju statusa.');
        }

        if (! $this->isConfigured($tenant->application)) {
            throw new RuntimeException(
                'Sinkronizacija statusa nije konfigurirana za aplikaciju "'.$tenant->application->slug.'". '
                .'Provjeri sync driver, API URL i API ključ.'
            );
        }

        $this->resolveDriver($tenant->application)->pushTenantStatus($tenant, $status);
    }

    public function pushPlan(Tenant $tenant, string $planSlug): void
    {
        $tenant->loadMissing('application');

        if ($tenant->application === null) {
            throw new RuntimeException('Tenant nema povezanu aplikaciju za sinkronizaciju plana.');
        }

        if (! $this->isConfigured($tenant->application)) {
            throw new RuntimeException(
                'Sinkronizacija plana nije konfigurirana za aplikaciju "'.$tenant->application->slug.'". '
                .'Provjeri sync driver, API URL i API ključ.'
            );
        }

        $this->resolveDriver($tenant->application)->pushTenantPlan($tenant, $planSlug);
    }

    /**
     * @param  array<string, mixed>  $remoteTenant
     */
    private function upsertTenant(Application $application, array $remoteTenant): void
    {
        $externalId = (string) ($remoteTenant['id'] ?? '');

        if ($externalId === '') {
            return;
        }

        $defaultPlan = $this->subscriptionPlanService
            ->defaultForApplication($application->id)?->slug ?? 'basic';

        $tenant = Tenant::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'external_id' => $externalId,
            ],
            [
                'name' => (string) ($remoteTenant['name'] ?? 'Nepoznato'),
                'slug' => (string) ($remoteTenant['slug'] ?? 'tenant-'.$externalId),
                'status' => (string) ($remoteTenant['status'] ?? TenantStatus::Pending->value),
                'plan' => (string) ($remoteTenant['plan'] ?? $defaultPlan),
                'synced_at' => now(),
            ],
        );

        if ($tenant->wasRecentlyCreated && $tenant->status === TenantStatus::Pending) {
            $this->superAdminNotificationService->notifyNewPendingTenant($tenant);
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function supportedApplicationSlugs(): Collection
    {
        $applications = config('saas_applications.applications', []);

        if (! is_array($applications)) {
            return collect();
        }

        return collect(array_keys($applications));
    }
}
