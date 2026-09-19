<?php

namespace App\Services\Admin\Sync;

class HrSaasSyncDriver extends AbstractSaasOrganizationSyncDriver
{
    protected function applicationLabel(): string
    {
        return 'hr-saas';
    }

    protected function devServerHint(): string
    {
        return 'Provjeri radi li server na http://127.0.0.1:8004.';
    }
}
