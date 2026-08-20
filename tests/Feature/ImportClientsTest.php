<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportClientsTest extends TestCase
{
    use RefreshDatabase;

    protected string $csvPath;

    protected function tearDown(): void
    {
        if (isset($this->csvPath) && file_exists($this->csvPath)) {
            unlink($this->csvPath);
        }

        parent::tearDown();
    }

    protected function writeCsv(string $contents): string
    {
        $this->csvPath = tempnam(sys_get_temp_dir(), 'clients_import_').'.csv';
        file_put_contents($this->csvPath, $contents);

        return $this->csvPath;
    }

    public function test_valid_rows_are_imported_into_the_given_company(): void
    {
        $company = Company::factory()->create();

        $path = $this->writeCsv(
            "name,phone,email,gender,city\n".
            "Ahmed Ali,+905551234567,ahmed@example.com,male,Istanbul\n".
            "Sara Yilmaz,+905559876543,sara@example.com,female,Ankara\n"
        );

        Artisan::call('clients:import', ['file' => $path, '--company' => $company->id]);

        $this->assertSame(2, Client::query()->where('company_id', $company->id)->count());

        $ahmed = Client::query()->where('name', 'Ahmed Ali')->first();
        $this->assertSame($company->id, $ahmed->company_id);
        $this->assertSame('ahmed@example.com', $ahmed->email);
        $this->assertSame('new', $ahmed->status->value);
        $this->assertNotEmpty($ahmed->client_code);
    }

    public function test_invalid_rows_are_skipped_and_reported_without_aborting_the_whole_file(): void
    {
        $company = Company::factory()->create();

        $path = $this->writeCsv(
            "name,phone,gender\n".
            "Valid Patient,+905551234567,male\n".
            ",+905550000000,male\n".
            "No Gender Patient,+905550000001,\n"
        );

        $exitCode = Artisan::call('clients:import', ['file' => $path, '--company' => $company->id]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, Client::query()->count());

        $output = Artisan::output();
        $this->assertStringContainsString('Imported 1 of 3 row(s)', $output);
        $this->assertStringContainsString('2 row(s) skipped', $output);
    }

    public function test_dry_run_validates_without_persisting_anything(): void
    {
        $company = Company::factory()->create();

        $path = $this->writeCsv(
            "name,phone,gender\n".
            "Dry Run Patient,+905551234567,male\n"
        );

        Artisan::call('clients:import', ['file' => $path, '--company' => $company->id, '--dry-run' => true]);

        $this->assertSame(0, Client::query()->count());
        $this->assertStringContainsString('[dry run] Imported 1 of 1 row(s)', Artisan::output());
    }

    public function test_import_fails_without_a_valid_company(): void
    {
        $path = $this->writeCsv("name,phone,gender\nSomeone,+905551234567,male\n");

        $exitCode = Artisan::call('clients:import', ['file' => $path, '--company' => 999999]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, Client::query()->count());
    }

    public function test_import_fails_when_the_file_is_missing_required_columns(): void
    {
        $company = Company::factory()->create();
        $path = $this->writeCsv("name,email\nSomeone,someone@example.com\n");

        $exitCode = Artisan::call('clients:import', ['file' => $path, '--company' => $company->id]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing required column', Artisan::output());
    }
}
