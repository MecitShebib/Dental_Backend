<?php

namespace App\Specialties\Dental;

use App\Models\Company;
use App\Models\Specialty;
use App\Specialties\SpecialtyModule;
use Database\Seeders\TreatmentCatalogSeeder;

/**
 * Thin adapter over the existing, live dental codebase -- deliberately NOT a
 * relocation of app/Services/AiTreatmentPlanService.php and friends into
 * this namespace. Moving ~a dozen dental-specific classes that 361+ tests
 * already exercise would be a large, purely mechanical, high-blast-radius
 * rename for zero behavior change; this class just gives dental the same
 * SpecialtyModule identity the other four specialties have, so code that
 * only knows about "the current specialty module" (the launcher, the
 * registry) can treat dental uniformly without needing a special case.
 */
class DentalModule implements SpecialtyModule
{
    public function __construct(protected TreatmentCatalogSeeder $catalogSeeder) {}

    public function key(): string
    {
        return Specialty::DENTAL;
    }

    public function brandName(): string
    {
        return 'Dentavaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    public function seedCatalog(Company $company): void
    {
        $this->catalogSeeder->seedCompany($company);
    }
}
