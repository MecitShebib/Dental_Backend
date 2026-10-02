<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gynecology_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('last_menstrual_period')->nullable();
            $table->integer('gravida')->nullable();
            $table->integer('para')->nullable();
            $table->integer('abortus')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('rh')->nullable();
            $table->integer('cycle_length_days')->nullable();
            $table->string('cycle_regularity')->nullable();
            $table->string('contraception_method')->nullable();
            $table->date('last_pap_smear_date')->nullable();
            $table->string('menopause_status')->nullable();
            $table->json('previous_delivery_types')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gynecology_client_profiles');
    }
};
