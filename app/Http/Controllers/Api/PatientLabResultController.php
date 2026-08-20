<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabResult\StorePatientLabResultRequest;
use App\Http\Requests\LabResult\UpdatePatientLabResultRequest;
use App\Http\Resources\PatientLabResultResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\PatientLabResult;
use App\Services\ClientSpecialtyEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The generic "test/analysis result" record for the 4 non-dental specialties
 * (Gynevaria/Medivaria/Orthovaria/Estevaria) -- see PatientLabResult's own
 * docblock for why this is a separate, much simpler table than LabCase
 * (dental's outsourced-prosthetics workflow). Shared/unprefixed routes, same
 * as visits/appointments/payments: there's no specialty-specific server
 * logic here (unlike the AI assistant's prompts/vocabulary), just a plain
 * client-scoped record whose specialty_id is derived from the treating
 * doctor, never trusted from client input.
 */
class PatientLabResultController extends Controller
{
    use AuthorizesOwnDoctorRecords, ResolvesTreatingDoctor;

    public function __construct(
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $labResults = $client->labResults()
            ->with(['doctor', 'appointment', 'specialty'])
            ->latest('test_date')
            ->get();

        return $this->success(PatientLabResultResource::collection($labResults));
    }

    public function store(StorePatientLabResultRequest $request, Client $client)
    {
        $data = $request->validated();
        $doctor = $this->resolveTreatingDoctor($request->user(), $data['doctor_id'] ?? null);
        $specialtyId = $this->resolveSpecialtyId($doctor);
        $appointment = $this->resolveAppointment($client, $data['appointment_id'] ?? null);

        $labResult = $client->labResults()->create([
            ...$data,
            'doctor_id' => $doctor->id,
            'specialty_id' => $specialtyId,
            'appointment_id' => $appointment?->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->enrollment->ensureEnrolled($client, $doctor);

        return $this->success(
            PatientLabResultResource::make($labResult->load(['doctor', 'appointment', 'specialty'])),
            'Lab result recorded successfully.',
            201,
        );
    }

    public function update(UpdatePatientLabResultRequest $request, PatientLabResult $labResult)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $labResult->doctor_id);

        $data = $request->validated();

        if (array_key_exists('doctor_id', $data)) {
            $doctor = $this->resolveTreatingDoctor($request->user(), $data['doctor_id']);
            $data['doctor_id'] = $doctor->id;
            $data['specialty_id'] = $this->resolveSpecialtyId($doctor);
        }

        if (array_key_exists('appointment_id', $data)) {
            $data['appointment_id'] = $this->resolveAppointment($labResult->client, $data['appointment_id'])?->id;
        }

        $labResult->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(
            PatientLabResultResource::make($labResult->load(['doctor', 'appointment', 'specialty'])),
            'Lab result updated successfully.',
        );
    }

    public function destroy(Request $request, PatientLabResult $labResult)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $labResult->doctor_id);

        $labResult->delete();

        return $this->success(null, 'Lab result deleted successfully.');
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
