<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Prescription;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every specialty's AI conversation now gets a shared block of context
 * (scheduling/appointments, prescriptions, visit/record history) it never
 * had before, plus a specialty-specific piece (dental: lab cases; the
 * other 4 non-nutrition specialties: lab results) -- see
 * AiConversationService::buildOpenAiMessages(). Exercised via gynecology
 * (representative of the 4 "plain" specialties) and dental.
 */
class AiConversationSharedContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
        ]);
    }

    protected function fakeChatResponse(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reply' => 'Understood.',
                        'options' => null,
                        'ready_for_plan' => false,
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
            ], 200),
        ]);
    }

    protected function makeDoctorAndClient(string $specialtyKey): array
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Sara',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $specialty->id,
            'primary_doctor_id' => $doctor->id,
        ]);
        $this->signKvkkConsent($client);

        return [$doctor, $client, $specialty];
    }

    protected function systemPromptFromLastRequest(): string
    {
        $captured = null;
        Http::assertSent(function ($request) use (&$captured) {
            $body = $request->data();
            $captured = collect($body['messages'])->firstWhere('role', 'system')['content'] ?? '';

            return true;
        });

        return (string) $captured;
    }

    public function test_a_same_day_appointment_tells_the_ai_not_to_book_another_one_today(): void
    {
        [$doctor, $client, $specialty] = $this->makeDoctorAndClient(Specialty::GYNECOLOGY);
        Sanctum::actingAs($doctor);

        Appointment::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'end_time' => '10:30',
            'planned_notes' => 'Routine checkup',
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);

        $this->fakeChatResponse();

        $this->postJson("/api/gynecology/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertOk();

        $prompt = $this->systemPromptFromLastRequest();
        $this->assertStringContainsString('The patient already has an appointment scheduled for today', $prompt);
        $this->assertStringContainsString('do NOT add another one for today', $prompt);
        $this->assertStringContainsString('Routine checkup', $prompt);
    }

    public function test_no_same_day_appointment_tells_the_ai_it_may_book_one_today(): void
    {
        [$doctor, $client] = $this->makeDoctorAndClient(Specialty::GYNECOLOGY);
        Sanctum::actingAs($doctor);

        $this->fakeChatResponse();

        $this->postJson("/api/gynecology/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertOk();

        $prompt = $this->systemPromptFromLastRequest();
        $this->assertStringContainsString('does not currently have an appointment scheduled for today', $prompt);
        $this->assertStringContainsString('appropriate to schedule the first session for today', $prompt);
    }

    public function test_prescriptions_and_visit_history_appear_in_the_context(): void
    {
        [$doctor, $client, $specialty] = $this->makeDoctorAndClient(Specialty::GYNECOLOGY);
        Sanctum::actingAs($doctor);

        $prescription = Prescription::create([
            'client_id' => $client->id,
            'specialty_id' => $specialty->id,
            'doctor_id' => $doctor->id,
            'prescribed_date' => now()->subDays(3)->toDateString(),
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);
        $prescription->items()->create([
            'medication_name' => 'Folic Acid',
            'dosage_instruction' => '1x1x30',
            'sort_order' => 0,
        ]);

        Visit::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->subDays(10)->toDateString(),
            'summary' => json_encode([
                '__visit_specialty_procedures__' => true,
                'procedures' => [['procedure_code' => 'prenatal_checkup', 'notes' => null]],
            ]),
            'notes' => 'Patient reported mild nausea.',
            'attendance_status' => 'attended',
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);

        $this->fakeChatResponse();

        $this->postJson("/api/gynecology/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertOk();

        $prompt = $this->systemPromptFromLastRequest();
        $this->assertStringContainsString('Recent prescriptions on file', $prompt);
        $this->assertStringContainsString('Folic Acid (1x1x30)', $prompt);
        $this->assertStringContainsString('Recent visit history', $prompt);
        $this->assertStringContainsString('procedures: prenatal_checkup', $prompt);
        $this->assertStringContainsString('Patient reported mild nausea.', $prompt);
    }

    public function test_dental_sees_lab_cases_instead_of_lab_results(): void
    {
        [$doctor, $client] = $this->makeDoctorAndClient(Specialty::DENTAL);
        Sanctum::actingAs($doctor);

        $client->labCases()->create([
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'teeth' => ['14'],
            'status' => 'in_progress',
            'sent_date' => now()->subDays(2)->toDateString(),
            'expected_return_date' => now()->addDays(5)->toDateString(),
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);

        $this->fakeChatResponse();

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertOk();

        $prompt = $this->systemPromptFromLastRequest();
        $this->assertStringContainsString('Dental lab cases on file', $prompt);
        $this->assertStringContainsString('teeth 14', $prompt);
        $this->assertStringContainsString('status: in_progress', $prompt);
    }
}
