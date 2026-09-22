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
     * How long a claim stays valid before the appointment becomes a candidate
     * again. Long enough that a queued job's own retries (three attempts) are
     * always finished first, so this never races a still-running send; short
     * enough that a reminder killed by a dead worker or an exhausted retry
     * chain is retried on the next cron run rather than lost for good.
     */
    public const CLAIM_TTL_MINUTES = 60;

    /**
     * Scheduled appointments starting within the next 24 hours that have a
     * client attached and haven't been reminded yet. Filtered on `date`
     * in SQL first (cheap), then precisely on the combined date+time in PHP
     * using the same start-datetime logic the rest of the app already uses.
     *
     * "Haven't been reminded yet" means two separate things now (see the
     * reminder_claimed_at migration): never actually delivered
     * (reminder_sent_at null) AND not currently being worked on by another
     * run (reminder_claimed_at null, or stale enough that whatever claimed
     * it is certainly dead).
     */
    public function candidates(): Collection
    {
        $now = now();
        $windowEnd = $now->copy()->addHours(24);
        $staleBefore = $now->copy()->subMinutes(self::CLAIM_TTL_MINUTES);

        return Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->whereNotNull('client_id')
            ->whereNull('reminder_sent_at')
            ->where(fn ($query) => $query
                ->whereNull('reminder_claimed_at')
                ->orWhere('reminder_claimed_at', '<=', $staleBefore))
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
     * command runs can't both send it. Returns false if it was already
     * claimed (or already delivered) -- the WHERE clause is re-evaluated by
     * the database inside the UPDATE, so the row is handed to exactly one
     * caller no matter how many run at once.
     *
     * Deliberately does NOT write reminder_sent_at: that is the permanent
     * "never try this again" flag, and claiming is not sending. Only
     * markSent() below, called after a channel has actually accepted the
     * message, may set it.
     */
    public function claim(Appointment $appointment): bool
    {
        $staleBefore = now()->subMinutes(self::CLAIM_TTL_MINUTES);

        return Appointment::query()
            ->where('id', $appointment->id)
            ->whereNull('reminder_sent_at')
            ->where(fn ($query) => $query
                ->whereNull('reminder_claimed_at')
                ->orWhere('reminder_claimed_at', '<=', $staleBefore))
            ->update(['reminder_claimed_at' => now()]) === 1;
    }

    /**
     * Records that the reminder really went out. Called only from the job,
     * only after send() reported a delivered channel.
     */
    public function markSent(Appointment $appointment): void
    {
        Appointment::query()
            ->where('id', $appointment->id)
            ->update(['reminder_sent_at' => now()]);
    }

    /**
     * Sends the reminder over every channel the client has.
     *
     * Returns whether the reminder can be considered delivered: true if at
     * least one channel accepted it, or if the client has no phone and no
     * email at all (nothing to deliver -- retrying would never help). False
     * means every channel that was tried failed, which the job turns into a
     * retry, and ultimately into a visible failed_jobs row with
     * reminder_sent_at still null so the appointment stays retryable.
     */
    public function send(Appointment $appointment): bool
    {
        $client = $appointment->client;

        if (! $client) {
            return true;
        }

        $smsAttempted = (bool) ($client->phone && $appointment->company);
        $emailAttempted = (bool) $client->email;

        if (! $smsAttempted && ! $emailAttempted) {
            return true;
        }

        $smsSent = $smsAttempted
            && $this->messaging->send($appointment->company, $client->phone, $this->smsText($appointment));

        // Mail::send() throws on a transport failure rather than returning
        // false, which the job already treats as a retryable failure.
        if ($emailAttempted) {
            Mail::to($client->email)->send(new AppointmentReminderMail($appointment));
        }

        $delivered = $smsSent || $emailAttempted;

        if (! $delivered) {
            Log::warning('Appointment reminder could not be delivered on any channel.', [
                'appointment_id' => $appointment->id,
                'client_id' => $client->id,
            ]);

            return false;
        }

        Log::info('Appointment reminder sent.', [
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'sms' => $smsSent,
            'email' => $emailAttempted,
        ]);

        return true;
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
            $appointment->doctor?->specialty_id,
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
