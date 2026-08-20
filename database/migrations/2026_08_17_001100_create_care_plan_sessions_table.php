<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            // Nullable only until the appointment is created inside the same
            // transaction that creates this row -- never actually null once
            // CarePlanService::confirmPlan() returns.
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('session_index');
            $table->string('title');
            $table->text('notes')->nullable();
            // Whatever structured data this session's specialty needs (e.g.
            // gynecology's trimester/vitals, orthopedics' post-op checklist)
            // -- deliberately schemaless here the same way TreatmentRecord.notes
            // already carries the dental odontogram's full JSON state; each
            // specialty module owns interpreting its own shape.
            $table->json('clinical_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_sessions');
    }
};
