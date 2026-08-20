<?php

namespace App\Specialties;

use App\Models\Company;

/**
 * The contract every "uzmanlık modülü" (specialty module) implements --
 * Dental today, Gynecology/InternalMedicine/Orthopedics/Cosmetic as stubs
 * until each gets its own clinical build-out. Kept deliberately small: only
 * what's actually needed by something real right now (catalog seeding,
 * identity for the launcher screen). Grow this contract when a second real
 * consumer needs a new method, not speculatively.
 */
interface SpecialtyModule
{
    /** Matches Specialty::key (Specialty::DENTAL etc). */
    public function key(): string;

    public function brandName(): string;

    /**
     * False for every specialty except dental today -- distinct from
     * Specialty::is_active (a company-subscribable flag an admin toggles)
     * because a specialty could in principle be turned on for subscriptions
     * before its clinical module is finished. Lets the launcher screen show
     * an honest "yakında" instead of a tile that looks real but leads
     * nowhere.
     */
    public function isBuilt(): bool;

    /**
     * Seed this specialty's treatment/procedure catalog for a newly
     * subscribing company. A no-op for a not-yet-built specialty.
     */
    public function seedCatalog(Company $company): void;
}
