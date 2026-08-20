<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionSurvey;
use App\Services\SatisfactionSurveyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SatisfactionSurveyPublicController extends Controller
{
    public function show(string $token)
    {
        $survey = SatisfactionSurvey::query()->where('token', $token)->firstOrFail();

        return view('public-survey', [
            'survey' => $survey,
            'companyName' => $survey->client->company?->name ?? '',
            'locale' => $survey->client->preferred_language?->value ?? 'en',
        ]);
    }

    public function submit(Request $request, string $token, SatisfactionSurveyService $surveys)
    {
        $survey = SatisfactionSurvey::query()->where('token', $token)->firstOrFail();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'wait_time_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'staff_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'cleanliness_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $surveys->submit($survey, (int) $data['rating'], $data['comment'] ?? null, [
                'wait_time_rating' => $data['wait_time_rating'] ?? null,
                'staff_rating' => $data['staff_rating'] ?? null,
                'cleanliness_rating' => $data['cleanliness_rating'] ?? null,
            ]);
        } catch (ValidationException) {
            // Already submitted -- fall through to the thank-you state.
        }

        return redirect()->route('survey.show', $token);
    }
}
