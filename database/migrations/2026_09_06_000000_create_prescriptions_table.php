<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cross-specialty "prescription" record requested for the patient
     * details page (all 5 specialties, dental included) -- plain
     * medication/dosage record-keeping, not a pharmacy/e-signature workflow.
     * Mirrors patient_lab_results' shape: specialty_id is derived
     * server-side from the treating doctor, never trusted from client input.
     */
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medication_name');
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->text('instructions')->nullable();
            $table->date('prescribed_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('client_id');
            $table->index('specialty_id');
            $table->index('doctor_id');
            $table->index('prescribed_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
