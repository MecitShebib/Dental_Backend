<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\LabCase;
use App\Models\PatientLabResult;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\Visit;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_at_least_one_of_everything_for_every_specialty(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach ([
            Specialty::DENTAL => 'DEMO-DEN-001',
            Specialty::GYNECOLOGY => 'DEMO-GYN-001',
            Specialty::INTERNAL_MEDICINE => 'DEMO-IM-001',
            Specialty::ORTHOPEDICS => 'DEMO-ORTHO-001',
            Specialty::COSMETIC => 'DEMO-COS-001',
        ] as $specialtyKey => $clientCode) {
            $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
            $client = Client::query()->where('client_code', $clientCode)->first();

            $this->assertNotNull($client, "Expected a seeded client for {$specialtyKey}");
            $this->assertDatabaseHas('subscriptions', ['specialty_id' => $specialty->id, 'status' => 'active']);
            $this->assertDatabaseHas('client_specialty_records', ['client_id' => $client->id, 'specialty_id' => $specialty->id]);
            $this->assertSame(1, Visit::query()->where('client_id', $client->id)->count(), "Expected one seeded visit for {$specialtyKey}");
            // >= 1, not exactly 1: the care plan below creates several of its
            // own appointments (one per session/milestone) on top of the one
            // standalone appointment seedAppointment() creates -- "at least
            // one" is the actual requirement, not "exactly one".
            $this->assertGreaterThanOrEqual(1, Appointment::query()->where('client_id', $client->id)->count(), "Expected at least one seeded appointment for {$specialtyKey}");
            $this->assertSame(1, Payment::query()->where('client_id', $client->id)->count(), "Expected one seeded payment for {$specialtyKey}");
            $this->assertSame(1, CarePlan::query()->where('client_id', $client->id)->count(), "Expected one seeded care plan for {$specialtyKey}");
            $this->assertSame(1, ClientConsent::query()->where('client_id', $client->id)->count(), "Expected one seeded consent for {$specialtyKey}");

            if ($specialtyKey === Specialty::DENTAL) {
                $this->assertSame(1, LabCase::query()->where('client_id', $client->id)->count());
            } else {
                $this->assertSame(1, PatientLabResult::query()->where('client_id', $client->id)->count(), "Expected one seeded lab result for {$specialtyKey}");
            }
        }

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('capital_transactions', 1);
        $this->assertDatabaseCount('salary_payments', 1);
        $this->assertDatabaseCount('cari_parties', 1);
        $this->assertDatabaseCount('cari_transactions', 1);
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('consent_templates', 1);

        // Every specialty's own gynecology/internal_medicine/orthopedics/cosmetic
        // treatment catalog must actually exist now (the real bug this task
        // surfaced -- seedCatalog() was previously never called for any of them).
        foreach (['prenatal_checkup', 'chronic_initial_assessment', 'ortho_assessment', 'cosmetic_consultation'] as $code) {
            $this->assertDatabaseHas('treatment_catalog', ['code' => $code]);
        }
    }

    public function test_running_it_twice_does_not_duplicate_records(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(1, Client::query()->where('client_code', 'DEMO-GYN-001')->count());
        $this->assertSame(5, Client::query()->where('client_code', 'like', 'DEMO-%')->count());
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('capital_transactions', 1);
        $this->assertDatabaseCount('salary_payments', 1);
        $this->assertDatabaseCount('cari_parties', 1);
        $this->assertDatabaseCount('cari_transactions', 1);
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('subscriptions', 5);

        $gynClient = Client::query()->where('client_code', 'DEMO-GYN-001')->firstOrFail();
        $this->assertSame(1, Visit::query()->where('client_id', $gynClient->id)->count());
        $this->assertSame(1, CarePlan::query()->where('client_id', $gynClient->id)->count());
    }
}
