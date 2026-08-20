<?php

namespace App\Console\Commands;

use App\Enums\ClientGender;
use App\Enums\ClientLanguage;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Bulk-imports patients from a CSV when a clinic migrates onto this system
 * from another one (DentSoft and similar competitors advertise this as a
 * free "data migration" service). Runs every row through the exact same
 * validation rules as the regular POST /clients endpoint (StoreClientRequest)
 * so imported data can't violate rules manual entry would catch, and reports
 * per-row failures instead of aborting the whole file on one bad row.
 */
class ImportClients extends Command
{
    /**
     * @var string
     */
    protected $signature = 'clients:import
        {file : Path to a CSV file with a header row}
        {--company= : Company id or uuid to import clients into}
        {--dry-run : Validate the file without saving anything}';

    /**
     * @var string
     */
    protected $description = 'Bulk-import patients from a CSV file (name, phone, email, gender, date_of_birth, city, address, medical_notes, preferred_language, status).';

    protected const REQUIRED_COLUMNS = ['name', 'phone'];

    protected const OPTIONAL_COLUMNS = [
        'email', 'gender', 'date_of_birth', 'city', 'address', 'medical_notes', 'preferred_language', 'status', 'client_code',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_string($path) || ! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $company = $this->resolveCompany();

        if (! $company) {
            return self::FAILURE;
        }

        $rows = $this->readCsv($path);

        if ($rows === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $imported = 0;
        $errors = [];

        foreach ($rows as $lineNumber => $row) {
            $data = $this->normalizeRow($row);

            $validator = Validator::make($data, [
                'client_code' => ['nullable', 'string', 'max:50', 'unique:clients,client_code'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['required', 'string', 'max:50'],
                'preferred_language' => ['nullable', Rule::enum(ClientLanguage::class)],
                'gender' => ['required', Rule::enum(ClientGender::class)],
                'date_of_birth' => ['nullable', 'date'],
                'city' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string'],
                'medical_notes' => ['nullable', 'string'],
                'status' => ['nullable', Rule::enum(ClientStatus::class)],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line {$lineNumber}: ".$validator->errors()->first();

                continue;
            }

            if (! $dryRun) {
                // Drop null-valued keys so absent optional columns (e.g.
                // preferred_language, which is NOT NULL with a DB default)
                // fall back to their column default instead of an explicit
                // null, the same as omitting the field from a JSON request.
                $attributes = array_filter($validator->validated(), fn ($value) => $value !== null);

                Client::create([
                    ...$attributes,
                    'company_id' => $company->id,
                    'client_code' => $data['client_code'] ?: 'CL-'.strtoupper(Str::random(8)),
                    'status' => $data['status'] ?: 'new',
                ]);
            }

            $imported++;
        }

        $this->info(($dryRun ? '[dry run] ' : '')."Imported {$imported} of ".count($rows).' row(s).');

        if ($errors) {
            $this->warn(count($errors).' row(s) skipped:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        return self::SUCCESS;
    }

    protected function resolveCompany(): ?Company
    {
        $identifier = $this->option('company');

        if (! $identifier) {
            $this->error('The --company option is required (id or uuid).');

            return null;
        }

        $company = is_numeric($identifier)
            ? Company::query()->find($identifier)
            : Company::query()->where('uuid', $identifier)->first();

        if (! $company) {
            $this->error("No company found matching --company={$identifier}.");

            return null;
        }

        return $company;
    }

    /**
     * @return array<int, array<string, string>>|null
     */
    protected function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open file: {$path}");

            return null;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('File is empty.');

            return null;
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing) {
            fclose($handle);
            $this->error('Missing required column(s): '.implode(', ', $missing));

            return null;
        }

        $rows = [];
        $lineNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }

            $rows[$lineNumber] = array_combine(
                array_slice($header, 0, count($line)),
                $line
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string|null>
     */
    protected function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ([...self::REQUIRED_COLUMNS, ...self::OPTIONAL_COLUMNS] as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            $normalized[$column] = $value === '' ? null : $value;
        }

        return $normalized;
    }
}
