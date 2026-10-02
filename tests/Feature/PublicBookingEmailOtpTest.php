<?php

namespace Tests\Feature;

use App\Mail\PublicBookingOtpMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * /book/{company} follows MOBILE_OTP_CHANNEL: with "email" the code goes by
 * email, email is required and the phone number optional; with "sms" the
 * phone is required and email optional (PublicBookingOtpTest covers SMS).
 */
class PublicBookingEmailOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.otp.channel' => 'email',
            'services.otp.fixed_code' => '123456',
            'services.iletimerkezi.enabled' => true,
            'services.iletimerkezi.api_key' => 'test-api-key',
            'services.iletimerkezi.api_hash' => 'test-api-hash',
        ]);
        Mail::fake();
        Http::fake();
    }

    protected function doctor(Company $company): User
    {
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'status' => 'active']);
        $doctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'slot_minutes' => 30])
            ->workingDays()->create(['weekday' => 'monday']);

        return $doctor;
    }

    protected function payload(User $doctor, array $overrides = []): array
    {
        return [
            'doctor_id' => $doctor->id,
            'date' => Carbon::now()->next(Carbon::MONDAY)->toDateString(),
            'start_time' => '09:00',
            'client_name' => 'Email Patient',
            'client_email' => 'patient@example.com',
            ...$overrides,
        ];
    }

    public function test_the_code_is_emailed_and_the_phone_is_optional(): void
    {
        Company::factory()->create(['booking_slug' => 'mail-clinic']);

        $response = $this->postJson('/api/public/companies/mail-clinic/book/request-otp', ['client_email' => 'patient@example.com'])
            ->assertOk()
            ->assertJsonPath('data.otp_channel', 'email');

        $this->assertStringContainsString('@example.com', $response->json('data.masked_destination'));
        Mail::assertSent(PublicBookingOtpMail::class, fn (PublicBookingOtpMail $mail) => $mail->hasTo('patient@example.com') && $mail->otp === '123456');
        Http::assertNothingSent();
        $this->assertDatabaseHas('public_booking_otps', ['email' => 'patient@example.com', 'mobile' => null]);
    }

    public function test_email_is_required_when_the_channel_is_email(): void
    {
        Company::factory()->create(['booking_slug' => 'mail-clinic']);

        $this->postJson('/api/public/companies/mail-clinic/book/request-otp', ['client_phone' => '+905551234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_email');
    }

    public function test_a_patient_books_with_only_an_email(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'mail-clinic']);
        $doctor = $this->doctor($company);
        $reference = $this->postJson('/api/public/companies/mail-clinic/book/request-otp', ['client_email' => 'patient@example.com'])->json('data.otp_reference');

        $this->postJson('/api/public/companies/mail-clinic/book', $this->payload($doctor, ['otp' => '123456', 'otp_reference' => $reference]))
            ->assertCreated();

        $client = Client::query()->where('company_id', $company->id)->where('email', 'patient@example.com')->firstOrFail();
        $this->assertSame('Email Patient', $client->name);
        $this->assertSame(1, $client->appointments()->count());
    }

    public function test_the_code_must_match_the_email_it_was_sent_to(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'mail-clinic']);
        $doctor = $this->doctor($company);
        $reference = $this->postJson('/api/public/companies/mail-clinic/book/request-otp', ['client_email' => 'patient@example.com'])->json('data.otp_reference');

        $this->postJson('/api/public/companies/mail-clinic/book', $this->payload($doctor, [
            'client_email' => 'someone-else@example.com', 'otp' => '123456', 'otp_reference' => $reference,
        ]))->assertUnprocessable()->assertJsonValidationErrors('otp_reference');
    }

    public function test_an_existing_client_is_matched_by_email(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'mail-clinic']);
        $doctor = $this->doctor($company);
        $existing = Client::create([
            'company_id' => $company->id, 'client_code' => 'CL-EXIST', 'name' => 'Existing',
            'phone' => '+905550001111', 'email' => 'Patient@Example.com', 'status' => 'new',
        ]);
        $reference = $this->postJson('/api/public/companies/mail-clinic/book/request-otp', ['client_email' => 'patient@example.com'])->json('data.otp_reference');

        $this->postJson('/api/public/companies/mail-clinic/book', $this->payload($doctor, ['otp' => '123456', 'otp_reference' => $reference]))
            ->assertCreated();

        $this->assertSame(1, Client::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, $existing->appointments()->count());
    }

    public function test_the_phone_is_required_and_email_optional_when_the_channel_is_sms(): void
    {
        // Provider off: the code is only logged, so no fake SMS response is needed.
        config(['services.otp.channel' => 'sms', 'services.iletimerkezi.enabled' => false]);
        Company::factory()->create(['booking_slug' => 'sms-clinic']);

        $this->postJson('/api/public/companies/sms-clinic/book/request-otp', ['client_email' => 'patient@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_phone');

        $this->postJson('/api/public/companies/sms-clinic/book/request-otp', ['client_phone' => '+905551234567'])
            ->assertOk()
            ->assertJsonPath('data.otp_channel', 'sms');
    }

    public function test_the_booking_page_knows_the_channel(): void
    {
        Company::factory()->create(['booking_slug' => 'mail-clinic']);

        $this->get('/book/mail-clinic')->assertOk()->assertSee('var otpChannel = "email";', false);

        config(['services.otp.channel' => 'sms']);
        $this->get('/book/mail-clinic')->assertOk()->assertSee('var otpChannel = "sms";', false);
    }
}
