<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_surgery_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('indication')->nullable();
            $table->string('planned_operation')->nullable();
            $table->string('asa_score')->nullable();
            $table->text('previous_surgeries')->nullable();
            $table->text('anticoagulant_use')->nullable();
            $table->text('allergies')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('rh')->nullable();
            $table->json('preop_checklist')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_surgery_client_profiles');
    }
};
