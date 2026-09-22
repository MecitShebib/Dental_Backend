<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Appointment $appointment) {}

    /**
     * The claim (reminder_claimed_at) was taken by the command before this
     * was dispatched, purely so two overlapping runs can't both send. The
     * permanent "delivered, never retry" marker (reminder_sent_at) is
     * written here and only here, after a channel has actually accepted the
     * message -- otherwise a send that failed every retry would leave the
     * appointment looking reminded and its message would be lost with no
     * trace beyond the queue's own failure record.
     */
    public function handle(AppointmentReminderService $reminders): void
    {
        if (! $reminders->send($this->appointment)) {
            throw new RuntimeException(
                "Appointment reminder delivery failed for appointment {$this->appointment->id}."
            );
        }

        $reminders->markSent($this->appointment);
    }

    /**
     * Retries are exhausted: the row is now in failed_jobs, and
     * reminder_sent_at is still null, so the next command run picks this
     * appointment up again once its claim goes stale (see
     * AppointmentReminderService::CLAIM_TTL_MINUTES).
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Appointment reminder job failed after all retries.', [
            'appointment_id' => $this->appointment->id,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
