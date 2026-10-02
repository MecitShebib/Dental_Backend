<?php

namespace App\Specialties;

use App\Specialties\Cosmetic\CosmeticModule;
use App\Specialties\Dental\DentalModule;
use App\Specialties\GeneralPractice\GeneralPracticeModule;
use App\Specialties\GeneralSurgery\GeneralSurgeryModule;
use App\Specialties\Gynecology\GynecologyModule;
use App\Specialties\Hematology\HematologyModule;
use App\Specialties\InternalMedicine\InternalMedicineModule;
use App\Specialties\Nutrition\NutritionModule;
use App\Specialties\Orthopedics\OrthopedicsModule;
use App\Specialties\Pediatrics\PediatricsModule;
use App\Specialties\Physiotherapy\PhysiotherapyModule;
use Illuminate\Support\Collection;

/**
 * Looks up the SpecialtyModule for a Specialty::key. All eleven constructor
 * args are concrete classes (not the SpecialtyModule interface), so the
 * container resolves this with no service-provider binding needed.
 */
class SpecialtyModuleRegistry
{
    /** @var array<string, SpecialtyModule> */
    protected array $modules;

    public function __construct(
        DentalModule $dental,
        GynecologyModule $gynecology,
        InternalMedicineModule $internalMedicine,
        OrthopedicsModule $orthopedics,
        CosmeticModule $cosmetic,
        NutritionModule $nutrition,
        PediatricsModule $pediatrics,
        PhysiotherapyModule $physiotherapy,
        HematologyModule $hematology,
        GeneralSurgeryModule $generalSurgery,
        GeneralPracticeModule $generalPractice,
    ) {
        $this->modules = [
            $dental->key() => $dental,
            $gynecology->key() => $gynecology,
            $internalMedicine->key() => $internalMedicine,
            $orthopedics->key() => $orthopedics,
            $cosmetic->key() => $cosmetic,
            $nutrition->key() => $nutrition,
            $pediatrics->key() => $pediatrics,
            $physiotherapy->key() => $physiotherapy,
            $hematology->key() => $hematology,
            $generalSurgery->key() => $generalSurgery,
            $generalPractice->key() => $generalPractice,
        ];
    }

    public function get(string $key): ?SpecialtyModule
    {
        return $this->modules[$key] ?? null;
    }

    /** @return Collection<string, SpecialtyModule> */
    public function all(): Collection
    {
        return collect($this->modules);
    }
}
