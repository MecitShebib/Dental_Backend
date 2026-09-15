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
use App\Models\PrescriptionItem;
use App\Services\ClientSpecialtyEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cross-specialty prescription record for the patient details page (all 5
 * specialties, dental included) -- plain medication/dosage record-keeping,
 * not a pharmacy/e-signature workflow. Mirrors PatientLabResultController's
 * shape: specialty_id is derived server-side from the treating doctor,
 * never trusted from client input.
 *
 * A prescription is a header (doctor, client, date) with one-or-more
 * PrescriptionItem lines (medication + a free-text "1x2x7"-style dosage
 * instruction) -- see syncItems(), which does a full delete-then-recreate
 * for the prescription's items, same replace-all convention as
 * TreatmentChargeService::syncItems.
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
            ->with(['doctor', 'appointment', 'specialty', 'items'])
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
            'doctor_id' => $doctor->id,
            'specialty_id' => $specialtyId,
            'appointment_id' => $appointment?->id,
            'prescribed_date' => $data['prescribed_date'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->syncItems($prescription, $data['items']);

        $this->enrollment->ensureEnrolled($client, $doctor);

        return $this->success(
            PrescriptionResource::make($prescription->load(['doctor', 'appointment', 'specialty', 'items'])),
            'Prescription recorded successfully.',
            201,
        );
    }

    public function update(UpdatePrescriptionRequest $request, Prescription $prescription)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $prescription->doctor_id);

        $data = $request->validated();
        $items = $data['items'] ?? null;
        unset($data['items']);

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

        if ($items !== null) {
            $this->syncItems($prescription, $items);
        }

        return $this->success(
            PrescriptionResource::make($prescription->load(['doctor', 'appointment', 'specialty', 'items'])),
            'Prescription updated successfully.',
        );
    }

    public function destroy(Request $request, Prescription $prescription)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $prescription->doctor_id);

        $prescription->delete();

        return $this->success(null, 'Prescription deleted successfully.');
    }

    /**
     * Distinct medication names this company has prescribed before, for the
     * "type VO, see Voltaren" typeahead on the frontend's item rows.
     * Company-wide rather than per-doctor -- more suggestions, and matches
     * how the treatment-product catalog is scoped company-wide rather than
     * per-doctor elsewhere in the app. PrescriptionItem carries no company
     * scope of its own; whereHas('prescription') runs the subquery through
     * Prescription::query(), which applies BelongsToCompanyViaClient's
     * global scope for us.
     */
    public function medicationSuggestions(Request $request)
    {
        $names = PrescriptionItem::query()
            ->whereHas('prescription')
            ->select('medication_name')
            ->distinct()
            ->orderBy('medication_name')
            ->limit(500)
            ->pluck('medication_name');

        return $this->success($names);
    }

    /**
     * Full delete-then-recreate for this prescription's items, same
     * replace-all convention as TreatmentChargeService::syncItems -- the
     * frontend's item table always sends its complete current row set, so a
     * partial payload would otherwise silently drop the rest.
     */
    protected function syncItems(Prescription $prescription, array $items): void
    {
        $prescription->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $prescription->items()->create([
                'medication_name' => $item['medication_name'],
                'dosage_instruction' => $item['dosage_instruction'] ?? null,
                'instructions' => $item['instructions'] ?? null,
                'sort_order' => $index,
            ]);
        }
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
