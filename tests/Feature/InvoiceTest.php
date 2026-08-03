<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creating_a_payment_automatically_issues_an_invoice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $client = $this->makeClient($user->company);

        $response = $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-08-03',
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertCreated();

        $response->assertJsonPath('data.invoice_number', 'INV-000001');
        $invoiceId = $response->json('data.invoice_id');

        $this->getJson("/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.formatted_number', 'INV-000001')
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.client.name', 'Test Patient');
    }

    public function test_invoice_numbers_increment_sequentially_per_company(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $client = $this->makeClient($user->company);

        foreach ([100, 200, 300] as $amount) {
            $this->postJson("/api/clients/{$client->id}/payments", [
                'payment_date' => '2026-08-03',
                'amount' => $amount,
                'payment_method' => 'cash',
            ])->assertCreated();
        }

        $numbers = collect($this->getJson("/api/clients/{$client->id}/payments")->json('data'))
            ->pluck('invoice_number')
            ->sort()
            ->values();

        $this->assertSame(['INV-000001', 'INV-000002', 'INV-000003'], $numbers->all());
    }

    public function test_two_companies_number_their_invoices_independently(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);

        Sanctum::actingAs($userA);
        $this->postJson("/api/clients/{$clientA->id}/payments", [
            'payment_date' => '2026-08-03', 'amount' => 100, 'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($userB);
        $response = $this->postJson("/api/clients/{$clientB->id}/payments", [
            'payment_date' => '2026-08-03', 'amount' => 100, 'payment_method' => 'cash',
        ])->assertCreated();

        // Company B's first invoice is also #1 -- numbering doesn't leak across companies.
        $response->assertJsonPath('data.invoice_number', 'INV-000001');
    }

    public function test_updating_a_payment_amount_updates_its_invoice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $client = $this->makeClient($user->company);

        $response = $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-08-03', 'amount' => 500, 'payment_method' => 'cash',
        ])->assertCreated();
        $paymentId = $response->json('data.id');
        $invoiceId = $response->json('data.invoice_id');

        $this->putJson("/api/payments/{$paymentId}", [
            'payment_date' => '2026-08-03', 'amount' => 750, 'payment_method' => 'cash',
        ])->assertOk();

        $this->getJson("/api/invoices/{$invoiceId}")->assertJsonPath('data.amount', 750);
    }

    public function test_deleting_a_payment_removes_its_invoice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $client = $this->makeClient($user->company);

        $response = $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-08-03', 'amount' => 500, 'payment_method' => 'cash',
        ])->assertCreated();
        $paymentId = $response->json('data.id');
        $invoiceId = $response->json('data.invoice_id');

        $this->deleteJson("/api/payments/{$paymentId}")->assertOk();

        $this->getJson("/api/invoices/{$invoiceId}")->assertNotFound();
    }

    public function test_a_user_cannot_view_another_companys_invoice(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherClient = $this->makeClient($otherCompany);

        Sanctum::actingAs($otherUser);
        $invoiceId = $this->postJson("/api/clients/{$otherClient->id}/payments", [
            'payment_date' => '2026-08-03', 'amount' => 500, 'payment_method' => 'cash',
        ])->assertCreated()->json('data.invoice_id');

        $ownUser = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($ownUser);

        $this->getJson("/api/invoices/{$invoiceId}")->assertNotFound();
    }
}
