<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Appointment $appointment) {}

    public function handle(AppointmentReminderService $reminders): void
    {
        $reminders->send($this->appointment);
    }
}
