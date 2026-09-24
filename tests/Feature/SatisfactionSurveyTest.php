<?php

namespace Tests\Feature;

use App\Mail\NegativeSatisfactionAlertMail;
use App\Mail\SatisfactionSurveyInviteMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\SatisfactionSurvey;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SatisfactionSurveyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.iletimerkezi.enabled' => true,
            'services.iletimerkezi.api_key' => 'test-api-key',
            'services.iletimerkezi.api_hash' => 'test-api-hash',
        ]);

        Http::fake([
            'https://api.iletimerkezi.com/*' => Http::response([
                'response' => ['status' => ['code' => 200, 'message' => 'OK'], 'order' => ['id' => 'test']],
            ], 200),
        ]);
    }

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_marking_a_visit_attended_creates_and_sends_a_survey_invite(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);

        Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);

        $this->assertSame(1, SatisfactionSurvey::query()->count());
        Http::assertSent(fn ($request) => str_contains((string) $request['request']['order']['message']['text'], '/survey/'));

        $survey = SatisfactionSurvey::query()->first();
        $this->assertNotNull($survey->invite_sent_at);
    }

    public function test_a_walk_in_or_no_show_visit_does_not_create_a_survey(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);

        Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'walk_in',
        ]);

        $this->assertSame(0, SatisfactionSurvey::query()->count());
    }

    public function test_updating_a_visit_to_attended_creates_exactly_one_survey_even_if_saved_again(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);

        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'walk_in',
        ]);

        $visit->update(['attendance_status' => 'attended']);
        $visit->update(['notes' => 'follow-up note']);

        $this->assertSame(1, SatisfactionSurvey::query()->count());
    }

    public function test_the_public_page_accepts_a_rating_and_marks_the_survey_submitted(): void
    {
        $company = Company::factory()->create(['name' => 'Verify Clinic']);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();

        $this->get("/survey/{$survey->token}")->assertOk()->assertSee('Verify Clinic');

        $this->post("/survey/{$survey->token}", ['rating' => 5, 'comment' => 'Great visit!'])
            ->assertRedirect(route('survey.show', $survey->token));

        $survey->refresh();
        $this->assertSame(5, $survey->rating);
        $this->assertSame('Great visit!', $survey->comment);
        $this->assertNotNull($survey->submitted_at);
    }

    public function test_submitting_twice_does_not_overwrite_the_first_rating(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();

        $this->post("/survey/{$survey->token}", ['rating' => 5]);
        $this->post("/survey/{$survey->token}", ['rating' => 1]);

        $this->assertSame(5, $survey->fresh()->rating);
    }

    public function test_an_unknown_token_returns_not_found(): void
    {
        $this->get('/survey/does-not-exist')->assertNotFound();
    }

    public function test_summary_computes_average_rating_and_distribution(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $doctor = User::factory()->create(['company_id' => $company->id]);

        foreach ([5, 5, 3] as $rating) {
            $client = $this->makeClient($company);
            $visit = Visit::create([
                'client_id' => $client->id, 'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
                'attendance_status' => 'attended',
            ]);
            $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();
            $this->post("/survey/{$survey->token}", ['rating' => $rating]);
        }

        $response = $this->getJson('/api/satisfaction-surveys/summary')->assertOk();

        $this->assertSame(3, $response->json('data.count'));
        $this->assertEquals(4.33, $response->json('data.average_rating'));
        $this->assertSame(2, $response->json('data.distribution.5'));
        $this->assertSame(1, $response->json('data.distribution.3'));
    }

    public function test_a_client_with_an_email_also_receives_an_invite_email(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email' => 'patient@example.com',
            'gender' => 'male',
            'status' => 'new',
        ]);

        Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);

        Mail::assertSent(SatisfactionSurveyInviteMail::class);
    }

    public function test_submitting_optional_category_ratings_are_stored(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();

        $this->post("/survey/{$survey->token}", [
            'rating' => 5,
            'wait_time_rating' => 4,
            'staff_rating' => 5,
            'cleanliness_rating' => 3,
        ]);

        $survey->refresh();
        $this->assertSame(4, $survey->wait_time_rating);
        $this->assertSame(5, $survey->staff_rating);
        $this->assertSame(3, $survey->cleanliness_rating);
    }

    public function test_a_low_rating_notifies_the_company_by_email(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['email' => 'clinic@example.com']);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();

        $this->post("/survey/{$survey->token}", ['rating' => 2, 'comment' => 'Long wait.']);

        Mail::assertSent(NegativeSatisfactionAlertMail::class, fn ($mail) => $mail->hasTo('clinic@example.com'));
    }

    public function test_a_good_rating_does_not_notify_the_company(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['email' => 'clinic@example.com']);
        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();

        $this->post("/survey/{$survey->token}", ['rating' => 4]);

        Mail::assertNotSent(NegativeSatisfactionAlertMail::class);
    }

    public function test_summary_includes_category_averages(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = $this->makeClient($company);
        $visit = Visit::create([
            'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $survey = SatisfactionSurvey::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->post("/survey/{$survey->token}", ['rating' => 5, 'wait_time_rating' => 3]);

        $response = $this->getJson('/api/satisfaction-surveys/summary')->assertOk();

        $this->assertEquals(3.0, $response->json('data.category_averages.wait_time'));
        $this->assertNull($response->json('data.category_averages.staff'));
    }

    public function test_summary_and_index_are_scoped_by_doctor_and_specialty(): void
    {
        $company = Company::factory()->create();
        $this->seed(\Database\Seeders\SpecialtySeeder::class);
        $dental = \App\Models\Specialty::query()->where('key', \App\Models\Specialty::DENTAL)->firstOrFail();
        $nutrition = \App\Models\Specialty::query()->where('key', \App\Models\Specialty::NUTRITION)->firstOrFail();

        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);
        $nutritionDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);

        $dentalClient = $this->makeClient($company);
        \App\Models\ClientSpecialtyRecord::create([
            'company_id' => $company->id, 'client_id' => $dentalClient->id,
            'specialty_id' => $dental->id, 'primary_doctor_id' => $dentalDoctor->id,
        ]);
        $dentalVisit = Visit::create([
            'client_id' => $dentalClient->id, 'doctor_id' => $dentalDoctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $dentalSurvey = SatisfactionSurvey::query()->where('visit_id', $dentalVisit->id)->firstOrFail();
        $this->post("/survey/{$dentalSurvey->token}", ['rating' => 5]);

        $nutritionClient = $this->makeClient($company);
        \App\Models\ClientSpecialtyRecord::create([
            'company_id' => $company->id, 'client_id' => $nutritionClient->id,
            'specialty_id' => $nutrition->id, 'primary_doctor_id' => $nutritionDoctor->id,
        ]);
        $nutritionVisit = Visit::create([
            'client_id' => $nutritionClient->id, 'doctor_id' => $nutritionDoctor->id,
            'visit_date' => now()->toDateString(), 'start_time' => '10:00:00', 'duration_minutes' => 30,
            'attendance_status' => 'attended',
        ]);
        $nutritionSurvey = SatisfactionSurvey::query()->where('visit_id', $nutritionVisit->id)->firstOrFail();
        $this->post("/survey/{$nutritionSurvey->token}", ['rating' => 3]);

        // A doctor sees only their own patients' surveys.
        Sanctum::actingAs($dentalDoctor);
        $this->getJson('/api/satisfaction-surveys/summary')->assertOk()->assertJsonPath('data.count', 1);

        // A non-doctor (admin) scoped to one specialty sees only that
        // specialty's surveys.
        Sanctum::actingAs($admin);
        $nutritionResponse = $this->getJson('/api/satisfaction-surveys/summary?specialty=nutrition')
            ->assertOk()->assertJsonPath('data.count', 1);
        $this->assertEquals(3.0, $nutritionResponse->json('data.average_rating'));

        // A non-doctor with no specialty filter still sees everything.
        $this->getJson('/api/satisfaction-surveys/summary')->assertOk()->assertJsonPath('data.count', 2);
    }
}
