<?php

namespace App\Console\Commands;

use App\Services\Admin\TenantSyncService;
use Illuminate\Console\Command;

class SyncTenantsCommand extends Command
{
    protected $signature = 'tenants:sync {slug? : Sinkroniziraj samo jednu aplikaciju po slug-u}';

    protected $description = 'Povuci tenant meta-podatke iz svih konfiguriranih SaaS aplikacija';

    public function handle(TenantSyncService $tenantSyncService): int
    {
        $slug = $this->argument('slug');
        $summary = $tenantSyncService->pullAllConfigured(is_string($slug) ? $slug : null);

        foreach ($summary['results'] as $applicationSlug => $result) {
            match ($result['status']) {
                'synced' => $this->info(sprintf(
                    '%s: sinkronizirano %d tenanata.',
                    $applicationSlug,
                    $result['tenants'] ?? 0,
                )),
                'skipped' => $this->line(sprintf(
                    '%s: preskočeno (%s).',
                    $applicationSlug,
                    $result['message'] ?? 'nepoznato',
                )),
                'failed' => $this->error(sprintf(
                    '%s: greška — %s',
                    $applicationSlug,
                    $result['message'] ?? 'nepoznata greška',
                )),
            };
        }

        if ($summary['synced_applications'] === 0 && $summary['failed_applications'] === 0) {
            $this->warn('Nema konfiguriranih aplikacija za sinkronizaciju.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf(
            'Završeno: %d tenanata, %d aplikacija sinkronizirano, %d preskočeno, %d neuspjelo.',
            $summary['synced_tenants'],
            $summary['synced_applications'],
            $summary['skipped_applications'],
            $summary['failed_applications'],
        ));

        return $summary['failed_applications'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
