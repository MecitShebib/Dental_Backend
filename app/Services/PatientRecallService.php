<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClientLanguage;
use App\Mail\PatientRecallMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\PatientRecall;
use App\Models\Visit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PatientRecallService
{
    public function __construct(
        protected MessagingService $messaging,
        protected MessageTemplateService $templates,
    ) {}

    /**
     * For every company with recalls enabled, each client's most recent
     * attended visit that has aged past the company's recall interval,
     * has no upcoming scheduled appointment, and hasn't already produced
     * a patient_recalls row (see the unique visit_id constraint -- once the
     * client visits again there's a new latest visit to become due again).
     *
     * @return Collection<int, array{client: Client, visit: Visit, due_at: Carbon}>
     */
    public function dueRecalls(): Collection
    {
        $results = collect();

        Company::query()->each(function (Company $company) use ($results): void {
            $intervalDays = $company->recallIntervalDays();

            if ($intervalDays === null) {
                return;
            }

            $cutoff = now()->subDays($intervalDays);

            Client::query()
                ->where('company_id', $company->id)
                ->whereDoesntHave('appointments', function ($query) {
                    $query->where('status', AppointmentStatus::Scheduled->value)->whereDate('date', '>=', now()->toDateString());
                })
                ->with(['visits' => function ($query) {
                    $query->where('attendance_status', AttendanceStatus::Attended->value)
                        ->latest('visit_date')
                        ->latest('id')
                        ->limit(1);
                }])
                ->get()
                ->each(function (Client $client) use ($results, $cutoff): void {
                    $visit = $client->visits->first();

                    if (! $visit || $visit->visit_date->greaterThan($cutoff)) {
                        return;
                    }

                    if (PatientRecall::query()->where('visit_id', $visit->id)->exists()) {
                        return;
                    }

                    $results->push([
                        'client' => $client,
                        'visit' => $visit,
                        'due_at' => $visit->visit_date->copy(),
                    ]);
                });
        });

        return $results->values();
    }

    /**
     * Atomically claims a visit for recalling so two overlapping command
     * runs can't both send it. Returns null if it was already claimed.
     */
    public function claim(Client $client, Visit $visit, \DateTimeInterface $dueAt): ?PatientRecall
    {
        try {
            return PatientRecall::create([
                'client_id' => $client->id,
                'visit_id' => $visit->id,
                'due_at' => $dueAt,
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    public function send(PatientRecall $recall): void
    {
        $client = $recall->client;

        if (! $client) {
            return;
        }

        if ($client->phone && $client->company) {
            $this->messaging->send($client->company, $client->phone, $this->smsText($client));
        }

        if ($client->email) {
            Mail::to($client->email)->send(new PatientRecallMail($recall));
        }

        $recall->update(['sent_at' => now()]);

        Log::info('Patient recall sent.', [
            'client_id' => $client->id,
            'visit_id' => $recall->visit_id,
            'sms' => (bool) $client->phone,
            'email' => (bool) $client->email,
        ]);
    }

    public function smsText(Client $client): string
    {
        return $this->render($client, 'sms')['body'];
    }

    public function emailSubject(Client $client): string
    {
        return $this->render($client, 'email')['subject'] ?? '';
    }

    public function emailBody(Client $client): string
    {
        return $this->render($client, 'email')['body'];
    }

    public function languageFor(Client $client): ClientLanguage
    {
        return $client->preferred_language ?? ClientLanguage::English;
    }

    /**
     * @return array{subject: ?string, body: string}
     */
    protected function render(Client $client, string $channel): array
    {
        if (! $client->company) {
            return ['subject' => null, 'body' => ''];
        }

        return $this->templates->render(
            $client->company,
            'patient_recall',
            $channel,
            $this->languageFor($client),
            [
                'client_name' => $client->name,
                'company_name' => $client->company->name,
            ],
        );
    }
}
