<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemManagerAiTreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
        ]);
    }

    protected function fakeOpenAiResponse(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Pulp necrosis on tooth 13.',
                        'sessions' => [
                            [
                                'day_offset' => 0,
                                'duration_minutes' => 30,
                                'session_description' => 'Open the canal and clean it.',
                                'teeth' => [],
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 80, 'total_tokens' => 200],
            ], 200),
        ]);
    }

    protected function doctorWithFullWeekSchedule(?Company $company = null): User
    {
        $doctor = User::factory()->create(array_filter([
            'is_doctor' => true,
            'company_id' => $company?->id,
        ]));

        Subscription::query()->firstOrCreate(
            ['company_id' => $doctor->company_id],
            [
                'plan_name' => 'Test Plan',
                'status' => 'active',
                'starts_at' => now()->subDay()->toDateString(),
                'max_users' => 10,
            ]
        );

        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function systemManager(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function makeClient(string $code = 'CL-4001'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Nour',
            'phone' => '+963900004001',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function sessionPayload(string $date): array
    {
        return [
            'date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'session_description' => 'Open the canal and clean it.',
            'odontogram_v2_status' => json_encode([
                'version' => '1.3',
                'globals' => [],
                'teeth' => ['13' => ['endo' => 'endo-filling-incomplete']],
            ]),
        ];
    }

    public function test_system_manager_can_preview_a_plan_for_a_selected_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $manager = $this->systemManager($doctor->company);
        Sanctum::actingAs($manager);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
            'doctor_id' => $doctor->id,
        ])->assertOk();

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'client_id' => $client->id,
            'action' => 'ai_treatment_plan_preview',
        ]);
    }

    public function test_system_manager_without_a_doctor_id_is_rejected(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $manager = $this->systemManager($doctor->company);
        Sanctum::actingAs($manager);
        $client = $this->makeClient('CL-4002');
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor_id');

        Http::assertNothingSent();
    }

    public function test_system_manager_cannot_pick_a_doctor_from_another_company(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $otherCompanyDoctor = $this->doctorWithFullWeekSchedule();
        $manager = $this->systemManager($doctor->company);
        Sanctum::actingAs($manager);
        $client = $this->makeClient('CL-4003');
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
            'doctor_id' => $otherCompanyDoctor->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor_id');

        Http::assertNothingSent();
    }

    public function test_plain_staff_user_without_a_role_is_still_rejected(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $receptionist = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($receptionist);
        $client = $this->makeClient('CL-4004');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
            'doctor_id' => $doctor->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor');
    }

    public function test_system_manager_can_confirm_a_plan_and_appointments_are_attributed_to_the_selected_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $manager = $this->systemManager($doctor->company);
        Sanctum::actingAs($manager);
        $client = $this->makeClient('CL-4005');
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $response = $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'doctor_id' => $doctor->id,
            'sessions' => [$this->sessionPayload($date)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $appointmentId = $response->json('data.0.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_doctor_confirming_a_plan_always_treats_under_their_own_schedule_even_if_a_doctor_id_is_sent(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $otherDoctor = $this->doctorWithFullWeekSchedule($doctor->company);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-4006');
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $response = $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'doctor_id' => $otherDoctor->id,
            'sessions' => [$this->sessionPayload($date)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'created_by' => $doctor->id,
        ]);
    }
}
