<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every client that exists before this migration predates the
 * client_specialty_records concept and was, in practice, always a dental
 * patient (Doctovaria's other specialties didn't exist yet) -- enroll them
 * all as dental patients so nobody "disappears" from the Patients page once
 * it starts filtering through this table. Best-effort infer
 * primary_doctor_id from each client's most recent appointment so existing
 * doctors don't lose visibility into patients they've actually treated;
 * left null (unassigned, visible only to system managers/admins until a
 * doctor next interacts with them) when there's no appointment history to
 * infer from.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Defensive: a deploy race once landed this table's *creation*
        // migration on production before its real column definitions had
        // synced (see the fix-columns migration's docblock for the full
        // story), which made THIS migration crash every time it tried to
        // query a column that didn't exist yet, permanently blocking every
        // migration after it in the same run (Laravel's migrator aborts a
        // run at the first failing migration). Skip cleanly if the table
        // isn't in the shape this migration expects -- the real backfill
        // now happens in a later migration that runs after the columns are
        // guaranteed to exist.
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
        // Intentionally irreversible -- there's no way to distinguish a
        // backfilled row from one a real dental patient enrollment created
        // moments after this migration ran.
    }
};
