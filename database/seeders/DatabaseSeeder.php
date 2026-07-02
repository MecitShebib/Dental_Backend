<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            TreatmentCatalogSeeder::class,
        ]);

        $company = Company::query()->updateOrCreate(
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

        $admin = User::query()->updateOrCreate(
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

        $projectAdmin = User::query()->updateOrCreate(
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

        $doctor = User::query()->updateOrCreate(
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

        $doctor2 = User::query()->updateOrCreate(
            ['email' => 'doctor2@clinic.com'],
            [
                'company_id' => $company->id,
                'uuid' => (string) Str::uuid(),
                'name' => 'Dr. Layan',
                'phone' => '+963900000003',
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
        $doctor2->roles()->sync([Role::query()->where('slug', 'doctor')->value('id')]);

        Subscription::query()->updateOrCreate(
            ['company_id' => $company->id, 'plan_name' => 'Clinic Company Plan'],
            [
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
