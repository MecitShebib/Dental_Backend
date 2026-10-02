<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pediatrics_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('gestational_age_weeks_at_birth')->nullable();
            $table->integer('birth_weight_g')->nullable();
            $table->decimal('birth_length_cm', 4, 1)->nullable();
            $table->decimal('birth_head_circumference_cm', 4, 1)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('feeding_type')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('rh')->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->json('developmental_milestones')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pediatrics_client_profiles');
    }
};
