<?php

namespace App\Http\Controllers\Api\InternalMedicine;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientListResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Specialty;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\Clinical\ClientQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Medivaria's own clinical Client endpoints. Thin delegation to the same
 * ClientQueryService dental's Api\ClientController uses. For index() and a
 * non-doctor's store(), the specialty is hardcoded to internal_medicine
 * rather than read from a query param -- the URL namespace itself declares
 * intent, so a caller can't override it. A DOCTOR's store() still enrolls
 * under the doctor's own specialty_id (via ClientSpecialtyEnrollmentService::
 * ensureEnrolled(), same as dental's own ClientController) since a doctor's
 * specialty is a fixed identity, not something the URL needs to disambiguate.
 * See docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 */
class ClientController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function __construct(
        protected ClientQueryService $clientQuery,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function index(IndexClientRequest $request)
    {
        $clients = $this->clientQuery->list($request->user(), Specialty::INTERNAL_MEDICINE, $request->validated());

        $resource = ClientListResource::collection($clients);

        return $this->success(
            $request->has('per_page') ? $resource->response()->getData(true) : $resource
        );
    }

    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        unset($data['specialty_id']);

        $actingUser = $request->user();

        $client = Client::create([
            ...$data,
            'client_code' => $data['client_code'] ?? 'CL-'.strtoupper(Str::random(8)),
            'created_by' => $actingUser->id,
            'updated_by' => $actingUser->id,
            'status' => $data['status'] ?? 'new',
        ]);

        if ($actingUser->is_doctor) {
            $this->enrollment->ensureEnrolled($client, $actingUser);
        } else {
            $internalMedicine = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
            $this->enrollment->ensureEnrolledForSpecialty($client, $internalMedicine, $actingUser);
        }

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client created successfully.', 201);
    }

    public function show(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

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
}
