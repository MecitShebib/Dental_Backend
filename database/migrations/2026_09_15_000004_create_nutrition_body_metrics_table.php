<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_body_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_at');
            $table->string('source')->default('manual');
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('bmi', 4, 1)->nullable();
            $table->decimal('body_fat_percent', 4, 1)->nullable();
            $table->decimal('muscle_mass_kg', 5, 1)->nullable();
            $table->decimal('visceral_fat_rating', 4, 1)->nullable();
            $table->decimal('water_percent', 4, 1)->nullable();
            $table->decimal('bone_mass_kg', 4, 1)->nullable();
            $table->integer('basal_metabolic_rate')->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('hip_cm', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->string('report_path')->nullable();
            $table->string('report_original_filename')->nullable();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_body_metrics');
    }
};
