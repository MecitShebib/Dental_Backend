<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClientLanguage;
use App\Models\Client;
use App\Models\Company;
use App\Models\PatientRecall;
use App\Models\Visit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PatientRecallService
{
    public function __construct(
        protected MessagingService $messaging,
        protected SystemMessageService $systemMessages,
        protected MessageTemplateVariableBuilder $templateVariables,
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

    /**
     * SMS only now (see SystemMessageService) -- email recalls were removed
     * outright, not just disabled.
     */
    public function send(PatientRecall $recall): void
    {
        $client = $recall->client;

        if (! $client || ! $client->phone || ! $client->company) {
            return;
        }

        $text = $this->smsText($client);

        if ($text === null) {
            return;
        }

        $this->messaging->send($client->company, $client->phone, $text);

        $recall->update(['sent_at' => now()]);

        Log::info('Patient recall sent.', [
            'client_id' => $client->id,
            'visit_id' => $recall->visit_id,
        ]);
    }

    /**
     * Null means there's no "System Messages" wording for this language any
     * more (staff deleted it).
     */
    public function smsText(Client $client): ?string
    {
        if (! $client->company) {
            return null;
        }

        $lastDoctor = $client->visits()
            ->where('attendance_status', AttendanceStatus::Attended->value)
            ->latest('visit_date')
            ->latest('id')
            ->first()?->doctor;

        $variables = $this->templateVariables->build($client, $lastDoctor, $client->company, $lastDoctor?->specialty?->key);

        return $this->systemMessages->bodyFor(
            $client->company,
            $lastDoctor?->specialty_id,
            'patient_recall',
            $this->languageFor($client)->value,
            $variables,
        );
    }

    public function languageFor(Client $client): ClientLanguage
    {
        return $client->preferred_language ?? ClientLanguage::English;
    }
}
