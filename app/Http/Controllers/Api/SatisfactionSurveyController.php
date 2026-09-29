<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SatisfactionSurveyResource;
use App\Models\SatisfactionSurvey;
use App\Models\Specialty;
use App\Services\SatisfactionSurveyService;
use Illuminate\Http\Request;

class SatisfactionSurveyController extends Controller
{
    public function index(Request $request)
    {
        $actingUser = $request->user();

        // Same "doctor hard-scoped to own specialty+patients, non-doctor
        // scoped by an explicit ?specialty= or unfiltered" rule as
        // ClientQueryService::list() -- a survey has no doctor_id of its
        // own, so this reaches the acting doctor's ownership through
        // client.specialtyRecords instead.
        $isDoctorOnly = $actingUser->isDoctorOnly();

        $surveys = SatisfactionSurvey::query()
            ->when($request->boolean('submitted_only'), fn ($query) => $query->whereNotNull('submitted_at'))
            ->when($isDoctorOnly, fn ($query) => $query->whereHas(
                'client.specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $actingUser->specialty_id)->where('primary_doctor_id', $actingUser->id)
            ))
            ->when(! $isDoctorOnly && $request->filled('specialty'), function ($query) use ($request) {
                $specialtyId = Specialty::query()->where('key', $request->string('specialty')->value())->value('id');
                $query->whereHas('client.specialtyRecords', fn ($sq) => $sq->where('specialty_id', $specialtyId));
            })
            ->with('client')
            ->latest('created_at')
            ->paginate($request->has('per_page') ? (int) $request->integer('per_page') : 25);

        return $this->success(SatisfactionSurveyResource::collection($surveys)->response()->getData(true));
    }

    public function summary(Request $request, SatisfactionSurveyService $surveys)
    {
        return $this->success($surveys->summary($request->user(), $request->string('specialty')->value() ?: null));
    }
}
