<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\PublicBookingOtp;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeExpiredPersonalDataTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-PURGE-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Purge Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_stale_otps_are_deleted_but_recent_ones_are_kept(): void
    {
        $user = User::factory()->create();
        $old = UserOtp::create(['user_id' => $user->id, 'mobile' => '905550000001', 'otp_code' => '111111', 'purpose' => 'login', 'reference' => 'ref-old', 'expires_at' => now()->subDays(2)->addMinutes(5)]);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        $recent = UserOtp::create(['user_id' => $user->id, 'mobile' => '905550000002', 'otp_code' => '222222', 'purpose' => 'login', 'reference' => 'ref-recent', 'expires_at' => now()->addMinutes(5)]);

        $oldBooking = PublicBookingOtp::create(['company_id' => Company::factory()->create()->id, 'mobile' => '905550000003', 'otp_code' => '333333', 'reference' => 'ref-booking-old', 'expires_at' => now()->subDays(2)->addMinutes(5)]);
        $oldBooking->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('kvkk:purge')->assertSuccessful();

        $this->assertDatabaseMissing('user_otps', ['id' => $old->id]);
        $this->assertDatabaseHas('user_otps', ['id' => $recent->id]);
        $this->assertDatabaseMissing('public_booking_otps', ['id' => $oldBooking->id]);
    }

    public function test_a_company_with_an_active_subscription_is_left_untouched(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subYear()->toDateString(),
            'ends_at' => null,
            'max_users' => 10,
        ]);
        $client = $this->makeClient($company->id);

        $this->artisan('kvkk:purge')->assertSuccessful();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Purge Test Patient', 'anonymized_at' => null]);
    }

    public function test_a_company_whose_subscription_lapsed_over_a_year_ago_has_its_clients_anonymized(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subYears(2)->toDateString(),
            'ends_at' => now()->subDays(400)->toDateString(),
            'max_users' => 10,
        ]);
        $client = $this->makeClient($company->id);

        $this->artisan('kvkk:purge')->assertSuccessful();

        $anonymized = Client::withoutGlobalScopes()->withTrashed()->findOrFail($client->id);
        $this->assertNotSame('Purge Test Patient', $anonymized->name);
        $this->assertNotNull($anonymized->anonymized_at);
    }

    public function test_a_company_whose_subscription_lapsed_recently_is_left_untouched(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subMonths(6)->toDateString(),
            'ends_at' => now()->subDays(30)->toDateString(),
            'max_users' => 10,
        ]);
        $client = $this->makeClient($company->id);

        $this->artisan('kvkk:purge')->assertSuccessful();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Purge Test Patient', 'anonymized_at' => null]);
    }

    public function test_a_company_that_never_had_a_subscription_is_left_untouched(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $client = $this->makeClient($company->id);

        $this->artisan('kvkk:purge')->assertSuccessful();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Purge Test Patient', 'anonymized_at' => null]);
    }
}
