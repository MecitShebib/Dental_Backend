<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\ClientLanguage;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppointmentReminderService
{
    public function __construct(
        protected AppointmentActionStateService $actionState,
        protected MessagingService $messaging,
        protected MessageTemplateService $templates,
    ) {}

    /**
     * Scheduled appointments starting within the next 24 hours that have a
     * client attached and haven't been reminded yet. Filtered on `date`
     * in SQL first (cheap), then precisely on the combined date+time in PHP
     * using the same start-datetime logic the rest of the app already uses.
     */
    public function candidates(): Collection
    {
        $now = now();
        $windowEnd = $now->copy()->addHours(24);

        return Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->whereNotNull('client_id')
            ->whereNull('reminder_sent_at')
            ->whereDate('date', '>=', $now->toDateString())
            ->whereDate('date', '<=', $windowEnd->toDateString())
            ->with(['client', 'doctor', 'company'])
            ->get()
            ->filter(function (Appointment $appointment) use ($now, $windowEnd) {
                $start = $this->actionState->startDateTime($appointment);

                return $start->greaterThan($now) && $start->lessThanOrEqualTo($windowEnd);
            })
            ->values();
    }

    /**
     * Atomically claims an appointment for reminding so two overlapping
     * command runs can't both send it. Returns false if it was already claimed.
     */
    public function claim(Appointment $appointment): bool
    {
        return Appointment::query()
            ->where('id', $appointment->id)
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]) === 1;
    }

    public function send(Appointment $appointment): void
    {
        $client = $appointment->client;

        if (! $client) {
            return;
        }

        if ($client->phone && $appointment->company) {
            $this->messaging->send($appointment->company, $client->phone, $this->smsText($appointment));
        }

        if ($client->email) {
            Mail::to($client->email)->send(new AppointmentReminderMail($appointment));
        }

        Log::info('Appointment reminder sent.', [
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'sms' => (bool) $client->phone,
            'email' => (bool) $client->email,
        ]);
    }

    public function smsText(Appointment $appointment): string
    {
        return $this->render($appointment, 'sms')['body'];
    }

    public function emailSubject(Appointment $appointment): string
    {
        return $this->render($appointment, 'email')['subject'] ?? '';
    }

    public function emailBody(Appointment $appointment): string
    {
        return $this->render($appointment, 'email')['body'];
    }

    public function languageFor(Appointment $appointment): ClientLanguage
    {
        return $appointment->client?->preferred_language ?? ClientLanguage::English;
    }

    /**
     * @return array{subject: ?string, body: string}
     */
    protected function render(Appointment $appointment, string $channel): array
    {
        if (! $appointment->company) {
            return ['subject' => null, 'body' => ''];
        }

        return $this->templates->render(
            $appointment->company,
            'appointment_reminder',
            $channel,
            $this->languageFor($appointment),
            $this->variables($appointment),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function variables(Appointment $appointment): array
    {
        $start = $this->actionState->startDateTime($appointment);

        return [
            'client_name' => $appointment->client?->name ?? '',
            'doctor_name' => $appointment->doctor?->name ?? '',
            'company_name' => $appointment->company?->name ?? '',
            'date' => $start->format('d/m/Y'),
            'time' => $start->format('H:i'),
        ];
    }
}
