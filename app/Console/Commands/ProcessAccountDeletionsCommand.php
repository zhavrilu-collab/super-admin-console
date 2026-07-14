<?php

namespace App\Console\Commands;

use App\Services\Gdpr\AccountErasureService;
use Illuminate\Console\Command;

class ProcessAccountDeletionsCommand extends Command
{
    protected $signature = 'gdpr:process-account-deletions';

    protected $description = 'Izvršava zakazana brisanja računa nakon grace perioda';

    public function handle(AccountErasureService $erasure): int
    {
        $processed = $erasure->processDueDeletions();

        $this->info('Obradjeno brisanja: '.$processed);

        return self::SUCCESS;
    }
}
