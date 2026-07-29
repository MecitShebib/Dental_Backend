<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Services\ClientFinancialSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFinancialSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(): Client
    {
        return Client::create([
            'client_code' => 'CL-9001',
            'name' => 'Test Client',
            'phone' => '+963900009001',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_summary_is_zero_with_no_charges_or_payments(): void
    {
        $client = $this->makeClient();

        $summary = app(ClientFinancialSummaryService::class)->summary($client);

        $this->assertSame(0.0, $summary['total_services_amount']);
        $this->assertSame(0.0, $summary['total_paid_amount']);
        $this->assertSame(0.0, $summary['remaining_amount']);
    }

    public function test_treatment_charges_from_every_source_sum_together(): void
    {
        $client = $this->makeClient();
        $client->treatmentCharges()->create(['source_type' => 'manual', 'amount' => 50]);
        $client->treatmentCharges()->create(['source_type' => 'visit', 'source_id' => 1, 'amount' => 25]);

        $summary = app(ClientFinancialSummaryService::class)->summary($client);

        $this->assertSame(75.0, $summary['total_services_amount']);
    }

    public function test_payments_deduct_from_the_charges_total(): void
    {
        $client = $this->makeClient();
        $client->treatmentCharges()->create(['source_type' => 'manual', 'amount' => 100]);
        $client->treatmentCharges()->create(['source_type' => 'manual', 'amount' => 50]);
        $client->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => 60,
            'payment_method' => 'cash',
        ]);

        $summary = app(ClientFinancialSummaryService::class)->summary($client);

        $this->assertSame(150.0, $summary['total_services_amount']);
        $this->assertSame(60.0, $summary['total_paid_amount']);
        $this->assertSame(90.0, $summary['remaining_amount']);
    }
}
