<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The very first migrate.php deploy for client_specialty_records raced a
 * WinSCP sync in progress: `php artisan make:migration` writes an empty
 * stub file first (just id() + timestamps()), and this repo's local->prod
 * file sync appears to have pushed that stub to production a moment before
 * the actual Write call replacing it with the real column definitions
 * landed -- the same class of timing issue as the .env-swap incidents (see
 * feedback_env_swap_playwright_risk memory), just hitting a freshly-created
 * migration file instead of .env this time. Laravel had already recorded
 * "2026_08_17_102439_create_client_specialty_records_table" as run against
 * that incomplete stub, so it will never re-run automatically even after
 * the file's real content synced moments later -- this migration adds
 * whatever's missing directly, guarded by hasColumn() so it's safe
 * regardless of exactly which columns did or didn't make it into the
 * original (broken) run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_specialty_records', function (Blueprint $table) {
            if (! Schema::hasColumn('client_specialty_records', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }
            if (! Schema::hasColumn('client_specialty_records', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('uuid')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('client_specialty_records', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('client_specialty_records', 'specialty_id')) {
                $table->foreignId('specialty_id')->nullable()->after('client_id')->constrained();
            }
            if (! Schema::hasColumn('client_specialty_records', 'primary_doctor_id')) {
                $table->foreignId('primary_doctor_id')->nullable()->after('specialty_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('client_specialty_records', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('primary_doctor_id')->constrained('users')->nullOnDelete();
            }
        });

        // uuid needs a real value + its unique index, but that has to happen
        // after the column exists and after any pre-existing rows (there
        // shouldn't be any real ones yet -- the broken table couldn't
        // accept a correct insert -- but be safe) are backfilled. A PHP-side
        // loop (not DB::raw('UUID()'), MySQL-only and not portable to the
        // sqlite connection the test suite runs against) so this migration
        // behaves identically on both.
        if (Schema::hasColumn('client_specialty_records', 'uuid')) {
            DB::table('client_specialty_records')
                ->where(fn ($q) => $q->whereNull('uuid')->orWhere('uuid', ''))
                ->select('id')
                ->get()
                ->each(fn ($row) => DB::table('client_specialty_records')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]));

            $indexes = collect(Schema::getIndexes('client_specialty_records'))->pluck('name');
            if (! $indexes->contains('client_specialty_records_uuid_unique')) {
                Schema::table('client_specialty_records', function (Blueprint $table) {
                    $table->unique('uuid');
                });
            }
            if (! $indexes->contains('client_specialty_records_client_id_specialty_id_unique')) {
                Schema::table('client_specialty_records', function (Blueprint $table) {
                    $table->unique(['client_id', 'specialty_id']);
                });
            }
        }
    }

    public function down(): void
    {
        // No safe generic down -- this is a one-off repair for a specific
        // broken deploy, not a reversible schema change.
    }
};
