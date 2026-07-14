<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingDunningService;
use Illuminate\Console\Command;

class ProcessBillingDunningCommand extends Command
{
    protected $signature = 'billing:process-dunning';

    protected $description = 'Pošalji dunning podsjetnike i suspendiraj tenante s neplaćenom pretplatom';

    public function handle(BillingDunningService $dunning): int
    {
        $result = $dunning->processScheduledActions();

        $this->info(sprintf(
            'Dunning obrada završena. Podsjetnika: %d, suspenzija: %d.',
            $result['reminders_sent'],
            $result['suspensions_applied'],
        ));

        return self::SUCCESS;
    }
}
