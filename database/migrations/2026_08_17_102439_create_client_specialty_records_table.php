<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client (the single, specialty-agnostic person record) becomes a
 * "patient" of a given specialty by having a row here -- the Zoho-Deals-like
 * membership record each specialty's Patients page and Dashboard filter
 * through. primary_doctor_id is nullable (a system manager can add/enroll a
 * patient without assigning a doctor yet) and is backfilled the first time a
 * doctor actually interacts with that client (see
 * ClientSpecialtyEnrollmentService) -- never overwritten afterward, so a
 * patient doesn't silently change "owner" just because a second doctor of
 * the same specialty later sees them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_specialty_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('primary_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_specialty_records');
    }
};
