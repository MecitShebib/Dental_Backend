<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Services\SpecialtyAiProfiles;
use App\Specialties\SpecialtyModuleRegistry;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI's structured plan output is constrained to
 * SpecialtyAiProfiles::procedureCodes(), and each proposed code is priced by
 * looking it up in that specialty's seeded TreatmentCatalog -- so the two
 * lists must stay identical, or the AI can propose a procedure that never
 * gets charged.
 */
class SpecialtyAiVocabularyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_milestone_specialty_ai_vocabulary_matches_its_seeded_catalog(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $registry = app(SpecialtyModuleRegistry::class);

        $keys = [
            Specialty::GYNECOLOGY,
            Specialty::INTERNAL_MEDICINE,
            Specialty::ORTHOPEDICS,
            Specialty::COSMETIC,
            Specialty::PEDIATRICS,
            Specialty::PHYSIOTHERAPY,
            Specialty::HEMATOLOGY,
            Specialty::GENERAL_SURGERY,
            Specialty::GENERAL_PRACTICE,
        ];

        foreach ($keys as $key) {
            $registry->get($key)->seedCatalog($company);
            $specialtyId = Specialty::query()->where('key', $key)->value('id');

            $catalogCodes = TreatmentCatalog::query()
                ->where('company_id', $company->id)
                ->where('specialty_id', $specialtyId)
                ->pluck('code')
                ->sort()
                ->values()
                ->all();
            $aiCodes = collect(SpecialtyAiProfiles::procedureCodes($key))->sort()->values()->all();

            $this->assertSame($catalogCodes, $aiCodes, "AI vocabulary and catalog differ for {$key}");
        }
    }
}
