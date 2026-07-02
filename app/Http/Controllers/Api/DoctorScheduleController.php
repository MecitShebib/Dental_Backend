<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorSchedule\UpdateDoctorScheduleRequest;
use App\Http\Resources\DoctorScheduleResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorScheduleController extends Controller
{
    public function show(User $doctor)
    {
        $this->assertDoctor($doctor);
        $schedule = $doctor->doctorSchedule()->with('workingDays')->firstOrCreate([
            'doctor_id' => $doctor->id,
        ], [
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);

        return $this->success(DoctorScheduleResource::make($schedule->load('workingDays')));
    }

    public function update(UpdateDoctorScheduleRequest $request, User $doctor)
    {
        $this->assertDoctor($doctor);

        $schedule = DB::transaction(function () use ($request, $doctor) {
            $schedule = $doctor->doctorSchedule()->updateOrCreate(
                ['doctor_id' => $doctor->id],
                [
                    'start_time' => $request->validated('start_time'),
                    'end_time' => $request->validated('end_time'),
                    'slot_minutes' => $request->validated('slot_minutes'),
                ]
            );

            $schedule->workingDays()->delete();
            $schedule->workingDays()->createMany(
                collect($request->validated('working_days'))->map(fn ($weekday) => ['weekday' => $weekday])->all()
            );

            return $schedule->load('workingDays');
        });

        return $this->success(DoctorScheduleResource::make($schedule), 'Doctor schedule updated successfully.');
    }

    protected function assertDoctor(User $doctor): void
    {
        if (! $doctor->is_doctor) {
            throw ValidationException::withMessages([
                'doctor' => ['The selected user is not a doctor.'],
            ]);
        }
    }
}
