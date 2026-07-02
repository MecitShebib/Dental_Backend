<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'system_manager' => 'System Manager',
            'receptionist' => 'Receptionist',
            'treatment_coordinator' => 'Treatment Coordinator',
            'doctor' => 'Doctor',
        ];

        foreach ($roles as $slug => $name) {
            Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $permissions = [
            'manage_users',
            'manage_clients',
            'manage_appointments',
            'manage_visits',
            'manage_payments',
            'manage_schedules',
        ];

        foreach ($permissions as $slug) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => str_replace('_', ' ', ucfirst($slug))]
            );
        }
    }
}
