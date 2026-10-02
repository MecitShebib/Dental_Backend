<?php

namespace Tests\Feature;

use App\Mail\NewAppointmentForDoctorMail;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every newly created appointment emails its doctor (date/time + patient
 * details), whichever code path created it -- see AppointmentObserver.
 */
class NewAppointmentDoctorEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAppointment(array $overrides = [], array $doctorAttrs = []): Appointment
    {
        $company = Company::factory()->create(['name' => 'Smile Clinic']);
        $doctor = User::factory()->create(array_merge([
            'company_id' => $company->id,
            'is_doctor' => true,
            'email' => 'doctor@example.com',
        ], $doctorAttrs));
        $manager = User::factory()->create(['company_id' => $company->id]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'P-0001',
            'name' => 'Ayşe Yılmaz',
            'phone' => '+905551112233',
            'email' => 'ayse@example.com',
            'gender' => 'female',
            'age' => 34,
        ]);

        return Appointment::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => '2026-10-05',
            'start_time' => '14:30',
            'end_time' => '15:00',
            'duration_minutes' => 30,
            'notes' => 'Tooth pain on the left side',
            'created_by' => $manager->id,
        ], $overrides));
    }

    public function test_doctor_is_emailed_with_time_and_patient_details(): void
    {
        Mail::fake();

        $this->makeAppointment();

        Mail::assertSent(NewAppointmentForDoctorMail::class, function (NewAppointmentForDoctorMail $mail) {
            $body = $mail->body();

            return $mail->hasTo('doctor@example.com')
                && str_contains($mail->render(), 'Smile Clinic')
                && str_contains($body, '2026-10-05')
                && str_contains($body, '14:30')
                && str_contains($body, 'Ayşe Yılmaz')
                && str_contains($body, '+905551112233')
                && str_contains($body, 'ayse@example.com')
                && str_contains($body, 'Tooth pain on the left side');
        });
    }

    public function test_no_email_when_the_doctor_booked_it_themselves(): void
    {
        Mail::fake();

        $appointment = $this->makeAppointment();
        Mail::fake();
        Appointment::create(array_merge($appointment->only([
            'company_id', 'client_id', 'doctor_id', 'type', 'status', 'duration_minutes',
        ]), ['date' => '2026-10-06', 'start_time' => '10:00', 'end_time' => '10:30', 'created_by' => $appointment->doctor_id]));

        Mail::assertNothingSent();
    }
}
