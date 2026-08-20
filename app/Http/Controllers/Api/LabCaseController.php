<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabCase\StoreLabCaseRequest;
use App\Http\Requests\LabCase\UpdateLabCaseRequest;
use App\Http\Resources\LabCaseResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\LabCase;
use App\Models\User;
use App\Services\LabCaseCariSyncService;
use App\Services\LabPaymentCostSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabCaseController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function __construct(
        protected LabPaymentCostSyncService $costSync,
        protected LabCaseCariSyncService $cariSync,
    ) {}

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $labCases = $client->labCases()
            ->with(['doctor', 'labPartner', 'appointment'])
            ->latest('sent_date')
            ->get();

        return $this->success(LabCaseResource::collection($labCases));
    }

    /**
     * Company-wide view across every client -- what a lab-workflow dashboard
     * needs ("which cases are overdue/ready right now"), unlike index()
     * which is scoped to one patient's own history.
     */
    public function all(Request $request)
    {
        $actingUser = $request->user();
        $doctorId = $actingUser->is_doctor ? $actingUser->id : $request->query('doctor_id');

        $labCases = LabCase::query()
            ->whereHas('client', fn ($q) => $q->where('company_id', $actingUser->company_id))
            ->with(['client', 'doctor', 'labPartner', 'appointment'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->latest('sent_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = LabCaseResource::collection($labCases);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreLabCaseRequest $request, Client $client)
    {
        $data = $request->validated();
        $this->assertActingDoctorOwnsDoctorId($request, $data['doctor_id']);
        $doctor = $this->resolveDoctor($data['doctor_id']);
        $appointment = $this->resolveAppointment($client, $data['appointment_id'] ?? null);

        $labCase = $client->labCases()->create([
            ...$data,
            'doctor_id' => $doctor->id,
            'appointment_id' => $appointment?->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->cariSync->sync($labCase, $request->user()->id);

        return $this->success(
            LabCaseResource::make($labCase->load(['doctor', 'labPartner', 'appointment'])),
            'Lab case recorded successfully.',
            201,
        );
    }

    public function update(UpdateLabCaseRequest $request, LabCase $labCase)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $labCase->doctor_id);

        $data = $request->validated();

        if (array_key_exists('doctor_id', $data)) {
            $this->assertActingDoctorOwnsDoctorId($request, $data['doctor_id']);
            $data['doctor_id'] = $this->resolveDoctor($data['doctor_id'])->id;
        }

        if (array_key_exists('appointment_id', $data)) {
            $data['appointment_id'] = $this->resolveAppointment($labCase->client, $data['appointment_id'])?->id;
        }

        $labCase->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $this->cariSync->sync($labCase, $request->user()->id);

        return $this->success(
            LabCaseResource::make($labCase->load(['doctor', 'labPartner', 'appointment'])),
            'Lab case updated successfully.',
        );
    }

    public function destroy(Request $request, LabCase $labCase)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $labCase->doctor_id);

        // Deleting the case cascades its lab_payments rows at the DB level,
        // but not their linked Expense/FundTransaction rows -- unwind those
        // explicitly first, same as removing each payment individually.
        foreach ($labCase->labPayments()->get() as $labPayment) {
            $this->costSync->remove($labPayment);
        }
        $this->cariSync->remove($labCase);

        $labCase->delete();

        return $this->success(null, 'Lab case deleted successfully.');
    }

    protected function resolveDoctor(int $doctorId): User
    {
        $doctor = User::query()->where('id', $doctorId)->where('is_doctor', true)->first();

        if (! $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => ['Please select a valid doctor.'],
            ]);
        }

        return $doctor;
    }

    protected function resolveAppointment(Client $client, ?int $appointmentId): ?Appointment
    {
        if (! $appointmentId) {
            return null;
        }

        // find() (not exists:appointments,id), so this stays scoped to the
        // acting user's own company via Appointment's BelongsToCompany scope --
        // and must belong to this same client, not just the same company.
        $appointment = Appointment::query()->where('id', $appointmentId)->where('client_id', $client->id)->first();

        if (! $appointment) {
            throw ValidationException::withMessages([
                'appointment_id' => ['Please select a valid appointment for this client.'],
            ]);
        }

        return $appointment;
    }
}
