<?php

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null for non-doctor staff (system manager/accountant/reception),
            // who work across every specialty their company subscribes to.
            // Set for a doctor -- a doctor belongs to exactly one specialty.
            $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        // Every doctor created before multi-specialty support is a dental
        // (Dentavaria) doctor.
        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');
        if ($dentalId) {
            User::query()->where('is_doctor', true)->whereNull('specialty_id')->update(['specialty_id' => $dentalId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
