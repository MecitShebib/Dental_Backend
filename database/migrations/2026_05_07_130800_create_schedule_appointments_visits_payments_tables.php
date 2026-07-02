<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('slot_minutes')->default(30);
            $table->timestamps();
        });

        Schema::create('doctor_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_schedule_id')->constrained()->cascadeOnDelete();
            $table->enum('weekday', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->timestamps();
            $table->unique(['doctor_schedule_id', 'weekday']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['booked', 'unavailable'])->index();
            $table->enum('status', ['scheduled', 'completed', 'no_show', 'cancelled'])->default('scheduled')->index();
            $table->date('date')->index();
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes');
            $table->time('end_time')->nullable();
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['doctor_id', 'date']);
            $table->index(['doctor_id', 'date', 'start_time']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date')->index();
            $table->time('start_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->longText('summary')->nullable();
            $table->longText('notes')->nullable();
            $table->enum('attendance_status', ['attended', 'no_show', 'walk_in']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('client_id');
            $table->index('doctor_id');
            $table->index('appointment_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer']);
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('client_id');
            $table->index('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctor_schedule_days');
        Schema::dropIfExists('doctor_schedules');
    }
};
