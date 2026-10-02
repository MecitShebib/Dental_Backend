<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CosmeticProcedureLog;
use App\Models\GynecologyClientProfile;
use App\Models\GynecologyUltrasoundExam;
use App\Models\HematologyBloodCount;
use App\Services\ClientErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * KVKK erasure must also remove the 2026-09-27 per-specialty clinical
 * profiles/records and their private-disk images (see
 * ClientErasureService::eraseSpecialtyClinicalData()).
 */
class SpecialtyClinicalDataErasureTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymizing_a_patient_deletes_specialty_clinical_rows_and_files(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-ERASE',
            'name' => 'Erase Me',
            'phone' => '+905550009999',
            'gender' => 'female',
            'status' => 'new',
        ]);

        Storage::disk('local')->put('gynecology_ultrasound_exams/scan.jpg', 'x');
        Storage::disk('local')->put('cosmetic_procedure_logs/before.jpg', 'x');

        $client->gynecologyProfile()->create(['gravida' => 2]);
        $client->gynecologyUltrasoundExams()->create(['exam_date' => '2026-09-01', 'image_path' => 'gynecology_ultrasound_exams/scan.jpg']);
        $client->hematologyBloodCounts()->create(['measured_at' => '2026-09-01', 'hb' => 10.5]);
        $client->cosmeticProcedureLogs()->create(['performed_at' => '2026-09-01', 'procedure_type' => 'Botox', 'before_photo_path' => 'cosmetic_procedure_logs/before.jpg']);

        app(ClientErasureService::class)->anonymize($client);

        $this->assertSame(0, GynecologyClientProfile::query()->count());
        $this->assertSame(0, GynecologyUltrasoundExam::query()->count());
        $this->assertSame(0, HematologyBloodCount::query()->count());
        $this->assertSame(0, CosmeticProcedureLog::query()->count());
        Storage::disk('local')->assertMissing('gynecology_ultrasound_exams/scan.jpg');
        Storage::disk('local')->assertMissing('cosmetic_procedure_logs/before.jpg');
    }
}
