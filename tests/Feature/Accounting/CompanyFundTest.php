<?php

namespace Tests\Feature\Accounting;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyFundTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
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

    public function test_a_patient_payment_credits_the_company_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $client = $this->makeClient($manager->company);

        $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-08-01',
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')
            ->assertOk()
            ->assertJsonPath('data.balance', 500);
    }

    public function test_updating_a_payment_adjusts_the_fund_balance(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $client = $this->makeClient($manager->company);

        $paymentId = $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-08-01',
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/payments/{$paymentId}", [
            'payment_date' => '2026-08-01',
            'amount' => 300,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 300);
    }

    public function test_deleting_a_payment_removes_it_from_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $client = $this->makeClient($manager->company);

        $paymentId = $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-08-01',
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/payments/{$paymentId}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }

    public function test_an_accountant_can_view_the_fund(): void
    {
        $company = Company::factory()->create();
        $accountant = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'accountant'], ['name' => 'Accountant']);
        $accountant->roles()->attach($role);
        Sanctum::actingAs($accountant);

        $this->getJson('/api/fund/summary')->assertOk();
        $this->getJson('/api/fund/transactions')->assertOk();
    }

    public function test_a_regular_user_cannot_view_the_fund(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/fund/summary')
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }

    public function test_fund_transactions_expose_pagination_metadata_when_per_page_is_requested(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $client = $this->makeClient($manager->company);

        foreach ([100, 200, 300] as $amount) {
            $this->postJson("/api/clients/{$client->id}/payments", [
                'payment_date' => '2026-08-01',
                'amount' => $amount,
                'payment_method' => 'cash',
            ])->assertCreated();
        }

        $response = $this->getJson('/api/fund/transactions?per_page=2')->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(3, $response->json('data.meta.total'));
        $this->assertNotNull($response->json('data.links.next'));

        // Without per_page, the response stays the existing flat shape.
        $this->getJson('/api/fund/transactions')->assertOk()->assertJsonMissingPath('data.meta');
    }

    public function test_fund_transactions_are_scoped_to_the_companys_own_data(): void
    {
        $manager = $this->makeManager();
        $otherCompany = Company::factory()->create();
        $otherManager = $this->makeManager($otherCompany);

        Sanctum::actingAs($otherManager);
        $otherClient = $this->makeClient($otherCompany);
        $this->postJson("/api/clients/{$otherClient->id}/payments", [
            'payment_date' => '2026-08-01',
            'amount' => 900,
            'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($manager);
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }
}
