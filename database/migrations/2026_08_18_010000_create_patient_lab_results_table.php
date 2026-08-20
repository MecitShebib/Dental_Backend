<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The generic "test/analysis result" record the 4 non-dental specialties
     * asked for (Gynevaria/Medivaria/Orthovaria/Estevaria) -- deliberately not
     * called "lab_cases" (that table is dental's own outsourced prosthetics
     * workflow: an external lab partner, cost, payments). This is plain
     * clinical record-keeping: a named test, its result, and whether it's in
     * range. No cost/payment tracking, since a test result isn't a billable
     * external job the way a crown/bridge case is.
     */
    public function up(): void
    {
        if (Schema::hasTable('patient_lab_results')) {
            return;
        }

        Schema::create('patient_lab_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Derived server-side from the treating doctor's own specialty_id
            // (same rule ClientSpecialtyEnrollmentService uses for
            // appointments) -- never trusted from client input.
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('test_name');
            $table->string('result_value')->nullable();
            $table->string('unit')->nullable();
            $table->string('reference_range')->nullable();
            $table->boolean('is_abnormal')->nullable();
            $table->date('test_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('client_id');
            $table->index('specialty_id');
            $table->index('doctor_id');
            $table->index('test_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_lab_results');
    }
};
