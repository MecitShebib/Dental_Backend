<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gynecology_ultrasound_exams', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('exam_date');
            $table->integer('gestational_week')->nullable();
            $table->integer('gestational_day')->nullable();
            $table->decimal('bpd_mm', 5, 1)->nullable();
            $table->decimal('hc_mm', 5, 1)->nullable();
            $table->decimal('ac_mm', 5, 1)->nullable();
            $table->decimal('fl_mm', 5, 1)->nullable();
            $table->integer('efw_grams')->nullable();
            $table->integer('fetal_heart_rate')->nullable();
            $table->string('placenta_location')->nullable();
            $table->string('amniotic_fluid')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_original_filename')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gynecology_ultrasound_exams');
    }
};
