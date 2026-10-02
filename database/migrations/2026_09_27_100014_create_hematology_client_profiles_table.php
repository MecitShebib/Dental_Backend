<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hematology_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('blood_type')->nullable();
            $table->string('rh')->nullable();
            $table->string('primary_diagnosis')->nullable();
            $table->text('diagnosis_notes')->nullable();
            $table->string('anticoagulant')->nullable();
            $table->decimal('inr_target_min', 3, 1)->nullable();
            $table->decimal('inr_target_max', 3, 1)->nullable();
            $table->boolean('splenectomy')->nullable();
            $table->text('chemo_protocol')->nullable();
            $table->integer('chemo_cycles_planned')->nullable();
            $table->integer('chemo_cycles_completed')->nullable();
            $table->text('bleeding_history')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hematology_client_profiles');
    }
};
