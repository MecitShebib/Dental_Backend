<?php

namespace App\Specialties;

use App\Specialties\Cosmetic\CosmeticModule;
use App\Specialties\Dental\DentalModule;
use App\Specialties\Gynecology\GynecologyModule;
use App\Specialties\InternalMedicine\InternalMedicineModule;
use App\Specialties\Orthopedics\OrthopedicsModule;
use Illuminate\Support\Collection;

/**
 * Looks up the SpecialtyModule for a Specialty::key. All five constructor
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
    ) {
        $this->modules = [
            $dental->key() => $dental,
            $gynecology->key() => $gynecology,
            $internalMedicine->key() => $internalMedicine,
            $orthopedics->key() => $orthopedics,
            $cosmetic->key() => $cosmetic,
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
