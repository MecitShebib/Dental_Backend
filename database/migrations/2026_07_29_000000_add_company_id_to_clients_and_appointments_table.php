<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index('company_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index('company_id');
        });

        // Backfill from the creating user's company. Any rows that remain
        // NULL after this (no created_by, or created_by's user has no
        // company) need manual review before company_id can be made
        // NOT NULL in a follow-up migration.
        DB::statement('
            UPDATE clients
            SET company_id = (SELECT company_id FROM users WHERE users.id = clients.created_by)
            WHERE created_by IS NOT NULL
        ');

        DB::statement('
            UPDATE appointments
            SET company_id = (SELECT company_id FROM users WHERE users.id = appointments.created_by)
            WHERE created_by IS NOT NULL
        ');

        // Fall back to the doctor's company for appointments still unset
        // (e.g. created_by missing but doctor_id is always present).
        DB::statement('
            UPDATE appointments
            SET company_id = (SELECT company_id FROM users WHERE users.id = appointments.doctor_id)
            WHERE company_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
