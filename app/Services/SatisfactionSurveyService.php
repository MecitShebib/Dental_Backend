<?php

namespace App\Services;

use App\Enums\ClientLanguage;
use App\Mail\NegativeSatisfactionAlertMail;
use App\Mail\SatisfactionSurveyInviteMail;
use App\Models\Company;
use App\Models\SatisfactionSurvey;
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
     * @return array{subject: ?string, body: string}
     */
    public function renderInvite(SatisfactionSurvey $survey, MessageTemplateService $templates, string $channel): array
    {
        $client = $survey->client;

        return $templates->render($client->company, 'satisfaction_survey', $channel, $client->preferred_language ?? ClientLanguage::English, [
            'client_name' => $client->name,
            'company_name' => $client->company->name,
            'survey_link' => url('/survey/'.$survey->token),
        ]);
    }

    public function languageFor(SatisfactionSurvey $survey): ClientLanguage
    {
        return $survey->client?->preferred_language ?? ClientLanguage::English;
    }

    /**
     * Sends on every channel the client has -- same "no channel left behind"
     * approach as AppointmentReminderService, so a client without a phone but
     * with an email (or vice versa) still gets invited.
     */
    public function sendInvite(SatisfactionSurvey $survey, MessagingService $messaging, MessageTemplateService $templates): void
    {
        $client = $survey->client;

        if (! $client || ! $client->company) {
            return;
        }

        if ($client->phone) {
            $messaging->send($client->company, $client->phone, $this->renderInvite($survey, $templates, 'sms')['body']);
        }

        if ($client->email) {
            Mail::to($client->email)->send(new SatisfactionSurveyInviteMail($survey));
        }

        if (! $client->phone && ! $client->email) {
            return;
        }

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
     * @return array{count: int, average_rating: ?float, distribution: array<int, int>, category_averages: array{wait_time: ?float, staff: ?float, cleanliness: ?float}}
     */
    public function summary(Company $company): array
    {
        $submitted = SatisfactionSurvey::query()
            ->whereHas('client', fn ($query) => $query->where('company_id', $company->id))
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
