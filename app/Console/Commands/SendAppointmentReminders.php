<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderJob;
use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Dispatch SMS/email reminders for appointments starting within the next 24 hours.';

    public function handle(AppointmentReminderService $reminders): int
    {
        $candidates = $reminders->candidates();
        $dispatched = 0;

        foreach ($candidates as $appointment) {
            if ($reminders->claim($appointment)) {
                SendAppointmentReminderJob::dispatch($appointment);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} appointment reminder(s).");

        return self::SUCCESS;
    }
}
