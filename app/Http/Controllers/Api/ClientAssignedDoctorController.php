<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lets a clinic's System Manager see and change which doctor a patient
 * belongs to within one specialty (client_specialty_records.primary_doctor_id
 * -- the same "owner" every doctor-scoped list and ownership check reads).
 * Normally that owner is claimed automatically by the first doctor who
 * books/sees the patient (ClientSpecialtyEnrollmentService); this is the
 * manual override.
 */
class ClientAssignedDoctorController extends Controller
{
    public function show(Request $request, Client $client)
    {
        $this->assertManager($request);
        $specialty = $this->specialty($request->validate([
            'specialty' => ['required', 'string', 'exists:specialties,key'],
        ])['specialty']);

        return $this->success($this->payload($client, $specialty));
    }

    public function update(Request $request, Client $client)
    {
        $this->assertManager($request);
        $data = $request->validate([
            'specialty' => ['required', 'string', 'exists:specialties,key'],
            'doctor_id' => ['required', 'integer'],
        ]);
        $specialty = $this->specialty($data['specialty']);

        $doctor = User::query()
            ->where('company_id', $request->user()->company_id)
            ->where('is_doctor', true)
            ->where('status', 'active')
            ->where('specialty_id', $specialty->id)
            ->find($data['doctor_id']);

        if (! $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => ['The selected doctor is not an active doctor of this specialty in your clinic.'],
            ]);
        }

        $record = ClientSpecialtyRecord::query()->firstOrNew([
            'client_id' => $client->id,
            'specialty_id' => $specialty->id,
        ]);
        $record->company_id ??= $client->company_id;
        $record->created_by ??= $request->user()->id;
        $record->primary_doctor_id = $doctor->id;
        $record->save();

        return $this->success($this->payload($client, $specialty), 'Patient assigned to the selected doctor.');
    }

    protected function assertManager(Request $request): void
    {
        abort_unless($request->user()->isSystemManager(), 403, 'Only the clinic administrator can change a patient\'s doctor.');
    }

    protected function specialty(string $key): Specialty
    {
        return Specialty::query()->where('key', $key)->firstOrFail();
    }

    protected function payload(Client $client, Specialty $specialty): array
    {
        $record = $client->specialtyRecords()->with('primaryDoctor:id,name')->where('specialty_id', $specialty->id)->first();

        return [
            'specialty' => $specialty->key,
            'doctor_id' => $record?->primary_doctor_id,
            'doctor_name' => $record?->primaryDoctor?->name,
        ];
    }
}
