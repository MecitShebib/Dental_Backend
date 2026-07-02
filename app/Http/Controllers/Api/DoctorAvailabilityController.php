<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailabilityResource;
use App\Models\User;
use App\Services\DoctorAvailabilityService;
use Illuminate\Http\Request;

class DoctorAvailabilityController extends Controller
{
    public function __construct(protected DoctorAvailabilityService $service)
    {
    }

    public function availability(Request $request, User $doctor)
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return $this->success(AvailabilityResource::make($this->service->availability($doctor, $data['date'])));
    }

    public function availableStartTimes(Request $request, User $doctor)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'in:30,60,90'],
        ]);

        return $this->success($this->service->availableStartTimes($doctor, $data['date'], (int) $data['duration_minutes']));
    }

    public function availableDurations(Request $request, User $doctor)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        return $this->success($this->service->availableDurations($doctor, $data['date'], $data['start_time']));
    }
}
