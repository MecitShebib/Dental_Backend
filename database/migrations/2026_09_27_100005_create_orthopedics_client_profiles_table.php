<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orthopedics_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('affected_region')->nullable();
            $table->string('side')->nullable();
            $table->date('complaint_onset_date')->nullable();
            $table->text('injury_mechanism')->nullable();
            $table->text('previous_surgeries_implants')->nullable();
            $table->string('cast_splint_status')->nullable();
            $table->text('occupation_sport')->nullable();
            $table->string('dominant_hand')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orthopedics_client_profiles');
    }
};
