<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_medicine_vitals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('measured_at');
            $table->integer('systolic')->nullable();
            $table->integer('diastolic')->nullable();
            $table->integer('pulse')->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->integer('spo2')->nullable();
            $table->integer('blood_glucose')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_medicine_vitals');
    }
};
