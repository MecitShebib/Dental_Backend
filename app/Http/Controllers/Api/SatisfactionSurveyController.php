<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SatisfactionSurveyResource;
use App\Models\SatisfactionSurvey;
use App\Services\SatisfactionSurveyService;
use Illuminate\Http\Request;

class SatisfactionSurveyController extends Controller
{
    public function index(Request $request)
    {
        $surveys = SatisfactionSurvey::query()
            ->when($request->boolean('submitted_only'), fn ($query) => $query->whereNotNull('submitted_at'))
            ->with('client')
            ->latest('created_at')
            ->paginate($request->has('per_page') ? (int) $request->integer('per_page') : 25);

        return $this->success(SatisfactionSurveyResource::collection($surveys)->response()->getData(true));
    }

    public function summary(Request $request, SatisfactionSurveyService $surveys)
    {
        return $this->success($surveys->summary($request->user()->company));
    }
}
