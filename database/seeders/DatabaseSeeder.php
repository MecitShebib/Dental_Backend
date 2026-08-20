<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Same as Model::updateOrCreate(), except it's safe for these seeded
     * demo rows specifically: (1) on a SoftDeletes model (User), a plain
     * updateOrCreate()'s lookup silently ignores a trashed row with the same
     * unique key, so it tries to INSERT a duplicate and dies on the DB-level
     * unique constraint -- restore instead. (2) survives a race where two
     * requests hit this seeder concurrently (public/migrate.php has no auth
     * and is meant to be deleted after deploying, per its own printed
     * warning, but a stray extra page load/bot hit before that happens is
     * enough to run this twice at once) by re-fetching (also withTrashed)
     * and updating instead of dying on the loser's INSERT.
     */
    protected function updateOrCreateSafely(string $modelClass, array $attributes, array $values): Model
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = fn () => $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();

        $existing = $query()->where($attributes)->first();

        if (! $existing) {
            try {
                $existing = $modelClass::create([...$attributes, ...$values]);
            } catch (UniqueConstraintViolationException) {
                $existing = $query()->where($attributes)->firstOrFail();
            }
        }

        $existing->fill($values);
        if ($usesSoftDeletes && $existing->trashed()) {
            $existing->restore();
        }
        $existing->save();

        return $existing;
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SpecialtySeeder::class,
        ]);

        $company = $this->updateOrCreateSafely(
            Company::class,
            ['code' => 'DENTAL-HQ'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Dental HQ',
                'email' => 'office@clinic.com',
                'phone' => '+963110000001',
                'address' => 'Damascus',
                'status' => 'active',
                'notes' => 'Seeded company',
            ]
        );

        // Explicit call for *this* company, in addition to (not instead of)
        // TreatmentCatalogSeeder::run() below -- that call backfills catalog
        // updates (new/changed items) to every *already-existing* company on
        // every deploy, which still matters and must keep happening. But on
        // a genuinely fresh install it runs with zero companies in the table
        // yet (this one didn't exist until the line above), so it would
        // silently seed nothing for this company -- only ever "fixed" in
        // practice by companies being created incrementally afterward via
        // CompanyController::store()'s own direct seedCompany() call. This
        // covers the fresh-install case; safe/idempotent to also run again
        // via the loop below.
        (new TreatmentCatalogSeeder)->seedCompany($company);

        $this->call([
            TreatmentCatalogSeeder::class,
        ]);

        $admin = $this->updateOrCreateSafely(
            User::class,
            ['email' => 'admin@clinic.com'],
            [
                'company_id' => $company->id,
                'uuid' => (string) Str::uuid(),
                'name' => 'Clinic Admin',
                'phone' => '+905342641738',
                'password' => Hash::make('secret'),
                'job_title' => 'System Manager',
                'branch_name' => 'Damascus',
                'status' => 'active',
                'is_project_admin' => false,
                'is_doctor' => false,
            ]
        );

        $projectAdmin = $this->updateOrCreateSafely(
            User::class,
            ['email' => 'project-admin@dental.com'],
            [
                // 'company_id' => $company->id,
                'uuid' => (string) Str::uuid(),
                'name' => 'Project Super Admin',
                'phone' => '+963900000000',
                'password' => Hash::make('123456'),
                'job_title' => 'Project Administrator',
                'branch_name' => 'Head Office',
                'status' => 'active',
                'is_project_admin' => true,
                'is_doctor' => false,
            ]
        );

        $doctor = $this->updateOrCreateSafely(
            User::class,
            ['email' => 'doctor@clinic.com'],
            [
                'company_id' => $company->id,
                'uuid' => (string) Str::uuid(),
                'name' => 'Dr. Layan',
                'phone' => '+963900000002',
                'password' => Hash::make('secret'),
                'job_title' => 'Doctor',
                'branch_name' => 'Damascus',
                'status' => 'active',
                'is_project_admin' => false,
                'is_doctor' => true,
            ]
        );

        $admin->roles()->sync([Role::query()->where('slug', 'system_manager')->value('id')]);
        $projectAdmin->roles()->sync([Role::query()->where('slug', 'system_manager')->value('id')]);
        $doctor->roles()->sync([Role::query()->where('slug', 'doctor')->value('id')]);

        $this->updateOrCreateSafely(
            Subscription::class,
            ['company_id' => $company->id, 'plan_name' => 'Clinic Company Plan'],
            [
                // On an already-migrated database this row predates the
                // specialty_id column and got backfilled to dental by that
                // migration; on a genuinely fresh install this seeder is the
                // one creating it, so it must set specialty_id itself.
                'specialty_id' => Specialty::query()->where('key', Specialty::DENTAL)->value('id'),
                'status' => 'active',
                'starts_at' => now()->subMonth()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
                'max_users' => 10,
                'active_users' => 3,
                'price' => 0,
                'notes' => 'Seeded company subscription',
            ]
        );
    }
}
