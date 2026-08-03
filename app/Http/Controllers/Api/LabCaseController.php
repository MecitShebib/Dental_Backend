<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabCase\StoreLabCaseRequest;
use App\Http\Requests\LabCase\UpdateLabCaseRequest;
use App\Http\Resources\LabCaseResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\LabCase;
use App\Models\User;
use App\Services\LabCaseCostSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabCaseController extends Controller
{
    public function __construct(protected LabCaseCostSyncService $costSync) {}

    public function index(Client $client)
    {
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
        $labCases = LabCase::query()
            ->whereHas('client', fn ($q) => $q->where('company_id', $request->user()->company_id))
            ->with(['client', 'doctor', 'labPartner', 'appointment'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('doctor_id'), fn ($q, $doctorId) => $q->where('doctor_id', $doctorId))
            ->latest('sent_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = LabCaseResource::collection($labCases);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreLabCaseRequest $request, Client $client)
    {
        $data = $request->validated();
        $doctor = $this->resolveDoctor($data['doctor_id']);
        $appointment = $this->resolveAppointment($client, $data['appointment_id'] ?? null);

        $labCase = $client->labCases()->create([
            ...$data,
            'doctor_id' => $doctor->id,
            'appointment_id' => $appointment?->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->costSync->sync($labCase, $request->user()->id);

        return $this->success(
            LabCaseResource::make($labCase->load(['doctor', 'labPartner', 'appointment'])),
            'Lab case recorded successfully.',
            201,
        );
    }

    public function update(UpdateLabCaseRequest $request, LabCase $labCase)
    {
        $data = $request->validated();

        if (array_key_exists('doctor_id', $data)) {
            $data['doctor_id'] = $this->resolveDoctor($data['doctor_id'])->id;
        }

        if (array_key_exists('appointment_id', $data)) {
            $data['appointment_id'] = $this->resolveAppointment($labCase->client, $data['appointment_id'])?->id;
        }

        $labCase->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $this->costSync->sync($labCase, $request->user()->id);

        return $this->success(
            LabCaseResource::make($labCase->load(['doctor', 'labPartner', 'appointment'])),
            'Lab case updated successfully.',
        );
    }

    public function destroy(Request $request, LabCase $labCase)
    {
        $this->costSync->removeExpense($labCase);
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
