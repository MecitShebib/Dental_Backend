<?php

namespace App\Services;

use App\Enums\ClientLanguage;
use App\Mail\NegativeSatisfactionAlertMail;
use App\Models\SatisfactionSurvey;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SatisfactionSurveyService
{
    public function createForVisit(Visit $visit): ?SatisfactionSurvey
    {
        if (! $visit->client) {
            return null;
        }

        if (SatisfactionSurvey::query()->where('visit_id', $visit->id)->exists()) {
            return null;
        }

        return SatisfactionSurvey::create([
            'client_id' => $visit->client_id,
            'visit_id' => $visit->id,
        ]);
    }

    /**
     * Null means there's no "System Messages" wording for this language any
     * more (staff deleted it) -- see SystemMessageService.
     */
    public function renderInvite(SatisfactionSurvey $survey, SystemMessageService $systemMessages, ?MessageTemplateVariableBuilder $templateVariables = null): ?string
    {
        $client = $survey->client;
        $doctor = $survey->visit?->doctor;
        $templateVariables ??= app(MessageTemplateVariableBuilder::class);

        $variables = $templateVariables->build($client, $doctor, $client->company, $doctor?->specialty?->key, [
            'survey_link' => url('/survey/'.$survey->token),
        ]);

        return $systemMessages->bodyFor(
            $client->company,
            $doctor?->specialty_id,
            'satisfaction_survey',
            $this->languageFor($survey)->value,
            $variables,
        );
    }

    public function languageFor(SatisfactionSurvey $survey): ClientLanguage
    {
        return $survey->client?->preferred_language ?? ClientLanguage::English;
    }

    /**
     * SMS only now (see SystemMessageService) -- email invites were removed
     * outright, not just disabled.
     */
    public function sendInvite(SatisfactionSurvey $survey, MessagingService $messaging, SystemMessageService $systemMessages): void
    {
        $client = $survey->client;

        if (! $client || ! $client->company || ! $client->phone) {
            return;
        }

        $text = $this->renderInvite($survey, $systemMessages);

        if ($text === null) {
            return;
        }

        $messaging->send($client->company, $client->phone, $text);

        $survey->update(['invite_sent_at' => now()]);

        Log::info('Satisfaction survey invite sent.', [
            'survey_id' => $survey->id,
            'client_id' => $client->id,
            'visit_id' => $survey->visit_id,
        ]);
    }

    /**
     * @param  array{wait_time_rating?: ?int, staff_rating?: ?int, cleanliness_rating?: ?int}  $categoryRatings
     */
    public function submit(SatisfactionSurvey $survey, int $rating, ?string $comment, array $categoryRatings = []): SatisfactionSurvey
    {
        if ($survey->isSubmitted()) {
            throw ValidationException::withMessages([
                'survey' => ['This survey has already been submitted.'],
            ]);
        }

        $survey->update([
            'rating' => $rating,
            'wait_time_rating' => $categoryRatings['wait_time_rating'] ?? null,
            'staff_rating' => $categoryRatings['staff_rating'] ?? null,
            'cleanliness_rating' => $categoryRatings['cleanliness_rating'] ?? null,
            'comment' => $comment,
            'submitted_at' => now(),
        ]);

        if ($survey->isNegative() && $survey->client?->company?->email) {
            Mail::to($survey->client->company->email)->send(new NegativeSatisfactionAlertMail($survey));
        }

        return $survey;
    }

    /**
     * Same doctor/specialty scoping as SatisfactionSurveyController::index()
     * -- $specialtyKey is only honored for a non-doctor acting user, a
     * doctor is always hard-scoped to their own specialty+patients.
     *
     * @return array{count: int, average_rating: ?float, distribution: array<int, int>, category_averages: array{wait_time: ?float, staff: ?float, cleanliness: ?float}}
     */
    public function summary(User $actingUser, ?string $specialtyKey): array
    {
        $isDoctorOnly = $actingUser->isDoctorOnly();

        $submitted = SatisfactionSurvey::query()
            ->whereHas('client', fn ($query) => $query->where('company_id', $actingUser->company_id))
            ->when($isDoctorOnly, fn ($query) => $query->whereHas(
                'client.specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $actingUser->specialty_id)->where('primary_doctor_id', $actingUser->id)
            ))
            ->when(! $isDoctorOnly && $specialtyKey, function ($query) use ($specialtyKey) {
                $specialtyId = Specialty::query()->where('key', $specialtyKey)->value('id');
                $query->whereHas('client.specialtyRecords', fn ($sq) => $sq->where('specialty_id', $specialtyId));
            })
            ->whereNotNull('submitted_at')
            ->get();

        $distribution = array_fill(1, 5, 0);

        foreach ($submitted as $survey) {
            $distribution[$survey->rating] = ($distribution[$survey->rating] ?? 0) + 1;
        }

        $categoryAverage = fn (string $column) => $submitted->whereNotNull($column)->count()
            ? round((float) $submitted->whereNotNull($column)->avg($column), 2)
            : null;

        return [
            'count' => $submitted->count(),
            'average_rating' => $submitted->count() ? round((float) $submitted->avg('rating'), 2) : null,
            'distribution' => $distribution,
            'category_averages' => [
                'wait_time' => $categoryAverage('wait_time_rating'),
                'staff' => $categoryAverage('staff_rating'),
                'cleanliness' => $categoryAverage('cleanliness_rating'),
            ],
        ];
    }
}
