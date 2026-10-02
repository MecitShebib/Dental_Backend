<?php

namespace Tests\Feature;

use App\Mail\SupportRequestMail;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionSeatLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpecialtySeeder::class);
    }

    /**
     * Essentials-shaped company: 3 doctors + 3 assistants. The manager
     * itself is a non-doctor, so it already takes 1 assistant seat.
     */
    protected function makeCompany(?int $maxDoctors = 3, ?int $maxAssistants = 3): array
    {
        $company = Company::factory()->create();
        $company->subscriptions()->update(['max_doctors' => $maxDoctors, 'max_assistants' => $maxAssistants]);

        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $manager->roles()->attach(Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']));

        return [$company, $manager];
    }

    protected function createUser(bool $isDoctor, int $n)
    {
        return $this->postJson('/api/users', [
            'name' => "User {$n}",
            'email' => "user{$n}@example.test",
            'password' => 'Password123!',
            'is_doctor' => $isDoctor,
            'specialty_id' => $isDoctor ? Specialty::query()->where('key', 'dental')->value('id') : null,
        ]);
    }

    public function test_doctor_cap_is_enforced_separately_from_assistants(): void
    {
        [, $manager] = $this->makeCompany();
        Sanctum::actingAs($manager);

        foreach ([1, 2, 3] as $n) {
            $this->createUser(true, $n)->assertCreated();
        }

        $this->createUser(true, 4)->assertStatus(422)->assertJsonValidationErrors('is_doctor');

        // Assistant seats are still free (manager uses 1 of 3).
        $this->createUser(false, 5)->assertCreated();
    }

    public function test_assistant_cap_counts_the_non_doctor_manager(): void
    {
        [, $manager] = $this->makeCompany();
        Sanctum::actingAs($manager);

        $this->createUser(false, 1)->assertCreated();
        $this->createUser(false, 2)->assertCreated();
        $this->createUser(false, 3)->assertStatus(422)->assertJsonValidationErrors('is_doctor');

        $this->createUser(true, 4)->assertCreated();
    }

    public function test_a_manager_who_is_a_doctor_takes_a_doctor_seat_not_an_assistant_seat(): void
    {
        [, $manager] = $this->makeCompany();
        $manager->update(['is_doctor' => true, 'specialty_id' => Specialty::query()->where('key', 'dental')->value('id')]);
        Sanctum::actingAs($manager);

        // 2 more doctors (manager is the 3rd)...
        $this->createUser(true, 1)->assertCreated();
        $this->createUser(true, 2)->assertCreated();
        $this->createUser(true, 3)->assertStatus(422)->assertJsonValidationErrors('is_doctor');

        // ...and all 3 assistant seats still free.
        $this->createUser(false, 4)->assertCreated();
        $this->createUser(false, 5)->assertCreated();
        $this->createUser(false, 6)->assertCreated();
        $this->createUser(false, 7)->assertStatus(422)->assertJsonValidationErrors('is_doctor');
    }

    public function test_switching_an_assistant_to_doctor_respects_the_doctor_cap(): void
    {
        [$company, $manager] = $this->makeCompany(1, 3);
        Sanctum::actingAs($manager);
        User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $assistant = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        $this->putJson("/api/users/{$assistant->id}", [
            'name' => $assistant->name,
            'email' => $assistant->email,
            'is_doctor' => true,
            'specialty_id' => Specialty::query()->where('key', 'dental')->value('id'),
        ])->assertStatus(422)->assertJsonValidationErrors('is_doctor');
    }

    public function test_blank_caps_mean_no_limit_for_that_seat_type(): void
    {
        [, $manager] = $this->makeCompany(null, null);
        Sanctum::actingAs($manager);

        foreach (range(1, 5) as $n) {
            $this->createUser($n % 2 === 0, $n)->assertCreated();
        }
    }

    public function test_support_info_reports_seat_usage_and_contact(): void
    {
        config(['services.support.email' => 'support@example.test', 'services.support.phone' => '+90 555 000 0000']);
        [, $manager] = $this->makeCompany();
        Sanctum::actingAs($manager);

        $this->getJson('/api/support/info')
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'support@example.test')
            ->assertJsonPath('data.contact.phone', '+90 555 000 0000')
            ->assertJsonPath('data.seats.doctors.limit', 3)
            ->assertJsonPath('data.seats.assistants.used', 1);
    }

    public function test_support_request_emails_the_support_inbox(): void
    {
        Mail::fake();
        config(['services.support.email' => 'support@example.test']);
        [, $manager] = $this->makeCompany();
        Sanctum::actingAs($manager);

        $this->postJson('/api/support/requests', [
            'type' => 'token_topup',
            'token_amount' => '5,000,000',
            'message' => 'Please top up our AI tokens.',
        ])->assertCreated();

        Mail::assertSent(SupportRequestMail::class, fn (SupportRequestMail $mail) => $mail->hasTo('support@example.test')
            && $mail->type === 'token_topup'
            && $mail->user->is($manager));
    }

    public function test_support_request_rejects_unknown_type(): void
    {
        [, $manager] = $this->makeCompany();
        Sanctum::actingAs($manager);

        $this->postJson('/api/support/requests', ['type' => 'free_money'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_landing_pages_show_the_configured_ai_token_price(): void
    {
        config(['services.support.ai_token_price_per_million' => '$7']);

        foreach (['dentavaria', 'gynevaria', 'medivaria', 'orthovaria', 'estevaria', 'dietavaria'] as $slug) {
            $this->get("/{$slug}")->assertOk()->assertSee('$7 per 1,000,000 tokens', false);
            $this->get("/ar/{$slug}")->assertOk()->assertSee('$7 لكل 1,000,000 توكن', false);
            $this->get("/tr/{$slug}")->assertOk()->assertSee('1.000.000 token için $7', false);
        }
    }
}
