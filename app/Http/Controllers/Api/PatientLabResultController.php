<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Concerns\ResolvesTreatingDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabResult\ExtractPatientLabResultRequest;
use App\Http\Requests\LabResult\StorePatientLabResultRequest;
use App\Http\Requests\LabResult\UpdatePatientLabResultRequest;
use App\Http\Resources\PatientLabResultResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\PatientLabResult;
use App\Services\AiTokenUsageService;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\OpenAiClient;
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
        protected OpenAiClient $openAi,
        protected AiTokenUsageService $aiTokenUsage,
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
        // Same gate index()/update()/destroy() already enforce -- missing
        // here meant any doctor in the company could record a lab result
        // onto a colleague's patient by client id (same class of bug fixed
        // in PrescriptionController::store()).
        $this->assertActingDoctorOwnsClient($request, $client);

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

    /**
     * Reads a photo of a lab/analysis report via OpenAI vision and returns
     * every test result it can find on it -- a single report routinely
     * lists many results at once (a full blood panel, say), unlike the
     * nutrition body-metric extraction this mirrors (one reading per
     * report). Nothing is persisted here; the frontend creates each
     * returned row via the plain store() endpoint above, same
     * extract-then-save split as everywhere else AI pre-fills a form.
     */
    public function analyze(ExtractPatientLabResultRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);
        $this->aiTokenUsage->assertCanUseAiTokens($request->user()->company);

        $messages = [
            ['role' => 'system', 'content' => $this->buildExtractionSystemPrompt()],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Extract every individual test result from this lab/analysis report.'],
                $this->openAi->buildVisionContentBlock($request->file('report')),
            ]],
        ];

        $response = $this->openAi->chatCompletionJson($messages, $this->extractionJsonSchema());

        $this->aiTokenUsage->recordUsage(
            $request->user()->company,
            $request->user(),
            $client,
            'patient_lab_result_extraction',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $response['usage']['prompt_tokens'],
            (int) $response['usage']['completion_tokens'],
        );

        return $this->success($response['content']);
    }

    protected function buildExtractionSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are reading a photo of a medical lab/analysis report (a blood panel,
            imaging report, or similar). A single report routinely lists several
            separate test results at once -- extract every one of them, not just the
            first. For each result, give: the test's name (test_name); its value as
            printed (result_value, as a plain string -- keep it exactly as shown,
            e.g. "7.2" or "Negative"); its unit if shown (unit, e.g. "g/dL", null if
            none); its reference/normal range if shown (reference_range, e.g.
            "12-16", null if none); whether the report itself flags this result as
            abnormal/out-of-range (is_abnormal: true/false if the report marks it
            one way or the other, null if not indicated). If the report shows a
            single date it applies to, return it as test_date in YYYY-MM-DD format
            for every result; otherwise return null (the frontend will default it to
            today). Never invent a result that isn't actually printed on the report.
            PROMPT;
    }

    protected function extractionJsonSchema(): array
    {
        $stringOrNull = ['type' => ['string', 'null']];

        return [
            'name' => 'patient_lab_result_extraction',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'results' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'test_name' => ['type' => 'string'],
                                'result_value' => $stringOrNull,
                                'unit' => $stringOrNull,
                                'reference_range' => $stringOrNull,
                                'is_abnormal' => ['type' => ['boolean', 'null']],
                                'test_date' => $stringOrNull,
                            ],
                            'required' => ['test_name', 'result_value', 'unit', 'reference_range', 'is_abnormal', 'test_date'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['results'],
                'additionalProperties' => false,
            ],
        ];
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
