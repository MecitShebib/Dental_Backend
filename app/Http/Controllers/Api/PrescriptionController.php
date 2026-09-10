<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Requests\Prescription\UpdatePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Prescription;
use App\Services\ClientSpecialtyEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cross-specialty prescription record for the patient details page (all 5
 * specialties, dental included) -- plain medication/dosage record-keeping,
 * not a pharmacy/e-signature workflow. Mirrors PatientLabResultController's
 * shape: specialty_id is derived server-side from the treating doctor,
 * never trusted from client input.
 */
class PrescriptionController extends Controller
{
    use AuthorizesOwnDoctorRecords, ResolvesTreatingDoctor;

    public function __construct(
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $prescriptions = $client->prescriptions()
            ->with(['doctor', 'appointment', 'specialty'])
            ->latest('prescribed_date')
            ->get();

        return $this->success(PrescriptionResource::collection($prescriptions));
    }

    public function store(StorePrescriptionRequest $request, Client $client)
    {
        // Same doctor-owns-this-patient gate index() already enforces --
        // missing here meant any doctor in the company could write a
        // prescription onto a colleague's patient by client id, since
        // resolveTreatingDoctor() only pins doctor_id to the acting doctor
        // (preventing impersonation) without ever checking whether $client
        // is actually theirs.
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();
        $doctor = $this->resolveTreatingDoctor($request->user(), $data['doctor_id'] ?? null);
        $specialtyId = $this->resolveSpecialtyId($doctor);
        $appointment = $this->resolveAppointment($client, $data['appointment_id'] ?? null);

        $prescription = $client->prescriptions()->create([
            ...$data,
            'doctor_id' => $doctor->id,
            'specialty_id' => $specialtyId,
            'appointment_id' => $appointment?->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->enrollment->ensureEnrolled($client, $doctor);

        return $this->success(
            PrescriptionResource::make($prescription->load(['doctor', 'appointment', 'specialty'])),
            'Prescription recorded successfully.',
            201,
        );
    }

    public function update(UpdatePrescriptionRequest $request, Prescription $prescription)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $prescription->doctor_id);

        $data = $request->validated();

        if (array_key_exists('doctor_id', $data)) {
            $doctor = $this->resolveTreatingDoctor($request->user(), $data['doctor_id']);
            $data['doctor_id'] = $doctor->id;
            $data['specialty_id'] = $this->resolveSpecialtyId($doctor);
        }

        if (array_key_exists('appointment_id', $data)) {
            $data['appointment_id'] = $this->resolveAppointment($prescription->client, $data['appointment_id'])?->id;
        }

        $prescription->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(
            PrescriptionResource::make($prescription->load(['doctor', 'appointment', 'specialty'])),
            'Prescription updated successfully.',
        );
    }

    public function destroy(Request $request, Prescription $prescription)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $prescription->doctor_id);

        $prescription->delete();

        return $this->success(null, 'Prescription deleted successfully.');
    }

    protected function resolveSpecialtyId(mixed $doctor): int
    {
        if (! $doctor->specialty_id) {
            throw ValidationException::withMessages([
                'doctor_id' => ['This doctor has no specialty assigned yet.'],
            ]);
        }

        return $doctor->specialty_id;
    }

    protected function resolveAppointment(Client $client, ?int $appointmentId): ?Appointment
    {
        if (! $appointmentId) {
            return null;
        }

        $appointment = Appointment::query()->where('id', $appointmentId)->where('client_id', $client->id)->first();

        if (! $appointment) {
            throw ValidationException::withMessages([
                'appointment_id' => ['Please select a valid appointment for this client.'],
            ]);
        }

        return $appointment;
    }
}
