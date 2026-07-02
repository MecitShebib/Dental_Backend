<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\CheckInAppointmentRequest;
use App\Http\Requests\Appointment\NoShowAppointmentRequest;
use App\Http\Requests\Visit\StoreVisitRequest;
use App\Http\Requests\Visit\UpdateVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientVisitController extends Controller
{
    public function index(Client $client)
    {
        $visits = $client->visits()->with(['doctor', 'appointment'])->latest('visit_date')->paginate();

        return $this->success(VisitResource::collection($visits));
    }

    public function store(StoreVisitRequest $request, Client $client)
    {
        $visit = $client->visits()->create([
            ...$request->validated(),
            'attendance_status' => AttendanceStatus::WalkIn->value,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $client->forceFill(['last_visit_at' => $visit->visit_date?->toDateString().' '.($visit->start_time ?? '00:00:00')])->save();

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Visit created successfully.', 201);
    }

    public function update(UpdateVisitRequest $request, Visit $visit)
    {
        $visit->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Visit updated successfully.');
    }

    public function destroy(Visit $visit)
    {
        $visit->delete();

        return $this->success(null, 'Visit deleted successfully.');
    }

    public function checkIn(CheckInAppointmentRequest $request, Appointment $appointment)
    {
        $visit = DB::transaction(function () use ($request, $appointment) {
            $this->assertAppointmentCanBeClosed($appointment);

            $visit = $appointment->visit()->create([
                'client_id' => $appointment->client_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->date,
                'start_time' => $appointment->start_time,
                'duration_minutes' => $appointment->duration_minutes,
                'summary' => $request->validated('summary'),
                'notes' => $request->validated('notes'),
                'attendance_status' => AttendanceStatus::Attended->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $appointment->update([
                'status' => AppointmentStatus::Completed->value,
                'updated_by' => $request->user()->id,
            ]);

            $appointment->client?->forceFill([
                'last_visit_at' => $appointment->date->format('Y-m-d').' '.$appointment->start_time,
            ])->save();

            return $visit;
        });

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Appointment checked in successfully.');
    }

    public function noShow(NoShowAppointmentRequest $request, Appointment $appointment)
    {
        $visit = DB::transaction(function () use ($request, $appointment) {
            $this->assertAppointmentCanBeClosed($appointment);

            $visit = $appointment->visit()->create([
                'client_id' => $appointment->client_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->date,
                'start_time' => $appointment->start_time,
                'duration_minutes' => $appointment->duration_minutes,
                'notes' => $request->validated('notes'),
                'attendance_status' => AttendanceStatus::NoShow->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $appointment->update([
                'status' => AppointmentStatus::NoShow->value,
                'updated_by' => $request->user()->id,
            ]);

            return $visit;
        });

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Appointment marked as no show successfully.');
    }

    protected function assertAppointmentCanBeClosed(Appointment $appointment): void
    {
        if ($appointment->status !== AppointmentStatus::Scheduled) {
            throw ValidationException::withMessages([
                'appointment' => ['Only scheduled appointments can be checked in or marked as no show.'],
            ]);
        }

        if ($appointment->visit()->exists()) {
            throw ValidationException::withMessages([
                'appointment' => ['This appointment already has a visit.'],
            ]);
        }
    }
}
