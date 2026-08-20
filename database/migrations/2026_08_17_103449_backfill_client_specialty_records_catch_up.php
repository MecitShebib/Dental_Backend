<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Originally written as a defensive re-run of the previous backfill
 * migration under uncertainty about whether it had actually succeeded.
 * Root cause is now confirmed (see the fix-columns migration's docblock): a
 * deploy race left client_specialty_records missing most of its columns,
 * which made both this and the previous backfill migration crash on every
 * attempt, permanently blocking every migration after them in the same run.
 * Guarded the same way as the previous one -- the real backfill now happens
 * in a later migration that runs after the columns are guaranteed to exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_specialty_records', 'client_id')) {
            return;
        }

        $dentalSpecialtyId = DB::table('specialties')->where('key', 'dental')->value('id');

        if (! $dentalSpecialtyId) {
            return;
        }

        $clients = DB::table('clients')->get(['id', 'company_id']);

        foreach ($clients as $client) {
            $alreadyEnrolled = DB::table('client_specialty_records')
                ->where('client_id', $client->id)
                ->where('specialty_id', $dentalSpecialtyId)
                ->exists();

            if ($alreadyEnrolled) {
                continue;
            }

            $primaryDoctorId = DB::table('appointments')
                ->where('client_id', $client->id)
                ->whereNotNull('doctor_id')
                ->orderByDesc('date')
                ->orderByDesc('start_time')
                ->value('doctor_id');

            DB::table('client_specialty_records')->insert([
                'uuid' => (string) Str::uuid(),
                'company_id' => $client->company_id,
                'client_id' => $client->id,
                'specialty_id' => $dentalSpecialtyId,
                'primary_doctor_id' => $primaryDoctorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Same as the migration this repeats -- irreversible for the same reason.
    }
};
