<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderJob;
use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $failed = 0;

        foreach ($candidates as $appointment) {
            if (! $reminders->claim($appointment)) {
                continue;
            }

            try {
                SendAppointmentReminderJob::dispatch($appointment);
                $dispatched++;
            } catch (Throwable $e) {
                // On the `sync` queue the job runs inline, so a failed send
                // surfaces here -- one bad recipient must not abort the rest
                // of the batch. The appointment keeps reminder_sent_at null,
                // so it is retried once its claim goes stale.
                $failed++;

                Log::error('Appointment reminder could not be dispatched.', [
                    'appointment_id' => $appointment->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Dispatched {$dispatched} appointment reminder(s).");

        if ($failed > 0) {
            $this->warn("{$failed} appointment reminder(s) failed to send and will be retried.");
        }

        return self::SUCCESS;
    }
}
