<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for DatabaseSeeder::run() against a genuinely empty
 * database. DatabaseSeeder wraps its whole run (including nested seeders
 * called via $this->call()) in WithoutModelEvents, which silently suppresses
 * HasUuid's "creating" event -- any nested seeder that relies on that event
 * instead of assigning a uuid itself will crash with a NOT NULL constraint
 * violation the very first time it runs against an empty table. This was
 * missed by every other test because they all seed SpecialtySeeder directly
 * (bypassing WithoutModelEvents) rather than through the full chain.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_seeder_chain_runs_against_a_fresh_database(): void
    {
        $this->seed();

        $this->assertSame(5, Specialty::query()->count());
        $this->assertNotNull(Specialty::query()->where('key', Specialty::DENTAL)->value('uuid'));

        $company = Company::query()->where('code', 'DENTAL-HQ')->firstOrFail();
        $this->assertTrue(User::query()->where('email', 'doctor@clinic.com')->exists());

        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');
        $this->assertTrue(
            Subscription::query()->where('company_id', $company->id)->where('specialty_id', $dentalId)->exists()
        );
        $this->assertTrue($company->activeSpecialties()->contains($dentalId));
    }
}
