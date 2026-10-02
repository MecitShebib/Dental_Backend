<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientListResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\TreatmentChargeResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Specialty;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\Clinical\ClientQueryService;
use App\Services\MessageTemplateVariableBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function __construct(
        protected ClientQueryService $clientQuery,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function index(IndexClientRequest $request)
    {
        $clients = $this->clientQuery->list(
            $request->user(),
            $request->filled('specialty') ? $request->string('specialty')->value() : null,
            $request->validated()
        );

        $resource = ClientListResource::collection($clients);

        return $this->success(
            $request->has('per_page') ? $resource->response()->getData(true) : $resource
        );
    }

    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        $specialtyId = $data['specialty_id'] ?? null;
        unset($data['specialty_id']);

        $actingUser = $request->user();

        // A user with their own branch_id set always creates records in
        // that branch -- overrides whatever branch_id the request sent,
        // the same rule doctors' specialty_id already gets everywhere else.
        // Only a user with no fixed branch (branch_id null, e.g. a
        // company-wide manager) falls back to whatever the request/frontend
        // (its own currently active branch) explicitly provided.
        $branchId = $actingUser->branch_id ?: ($data['branch_id'] ?? null);

        $client = Client::create([
            ...$data,
            'branch_id' => $branchId,
            'client_code' => $data['client_code'] ?? 'CL-'.strtoupper(Str::random(8)),
            'created_by' => $actingUser->id,
            'updated_by' => $actingUser->id,
            'status' => $data['status'] ?? 'new',
        ]);

        if ($actingUser->is_doctor) {
            $this->enrollment->ensureEnrolled($client, $actingUser);
        } elseif ($specialtyId && $specialty = Specialty::find($specialtyId)) {
            $this->enrollment->ensureEnrolledForSpecialty($client, $specialty, $actingUser);
        }

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client created successfully.', 201);
    }

    public function show(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        AuditLog::record('viewed', $client, $request->user());

        $client->load([
            ...$this->clientQuery->nextAppointmentEagerLoad(),
            'treatmentRecord',
        ]);

        return $this->success(ClientResource::make($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $client->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client updated successfully.');
    }

    public function destroy(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $client->delete();

        return $this->success(null, 'Client deleted successfully.');
    }

    /**
     * The "Service Log" -- every treatment_charges line item for this
     * client (manual fees, AI plan sessions, visits, appointments,
     * inventory sales), newest first, so the Payments tab can show not just
     * what they've paid but where a balance actually came from.
     */
    public function treatmentCharges(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $charges = $client->treatmentCharges()
            ->with('creator')
            ->latest('created_at')
            ->latest('id')
            ->get();

        return $this->success(TreatmentChargeResource::collection($charges));
    }

    /**
     * Every variable the "#" picker (Settings > Message Templates and its
     * Custom WhatsApp Messages section) can insert, resolved for THIS client
     * right now -- reuses MessageTemplateVariableBuilder, the exact same
     * resolution the automated send pathways (reminders, recalls, booking
     * confirmations, satisfaction surveys) already use, so a token inserted
     * here behaves identically to one in a built-in template. date/time have
     * no real appointment to anchor to in this ad-hoc context, so they
     * resolve to right now.
     */
    public function messageVariables(Request $request, Client $client, MessageTemplateVariableBuilder $templateVariables)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $specialtyKey = $request->filled('specialty') ? $request->string('specialty')->value() : null;
        $specialtyId = $specialtyKey ? Specialty::query()->where('key', $specialtyKey)->value('id') : null;

        $doctor = $specialtyId
            ? $client->specialtyRecords()->where('specialty_id', $specialtyId)->first()?->primaryDoctor
            : null;

        $variables = $templateVariables->build(
            $client,
            $doctor,
            $client->company,
            $specialtyKey,
            [
                'date' => now()->format('d/m/Y'),
                'time' => now()->format('H:i'),
            ],
        );

        return $this->success($variables);
    }
}
