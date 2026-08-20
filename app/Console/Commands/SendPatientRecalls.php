<?php

namespace App\Console\Commands;

use App\Jobs\SendPatientRecallJob;
use App\Services\PatientRecallService;
use Illuminate\Console\Command;

class SendPatientRecalls extends Command
{
    /**
     * @var string
     */
    protected $signature = 'patients:send-recalls';

    /**
     * @var string
     */
    protected $description = 'Dispatch SMS/email follow-up recalls for patients overdue for a check-up.';

    public function handle(PatientRecallService $recalls): int
    {
        $dispatched = 0;

        foreach ($recalls->dueRecalls() as $due) {
            $recall = $recalls->claim($due['client'], $due['visit'], $due['due_at']);

            if ($recall) {
                SendPatientRecallJob::dispatch($recall);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} patient recall(s).");

        return self::SUCCESS;
    }
}
