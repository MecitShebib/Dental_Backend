<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosmetic_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fitzpatrick_skin_type')->nullable();
            $table->text('aesthetic_goals')->nullable();
            $table->json('areas_of_interest')->nullable();
            $table->text('previous_procedures')->nullable();
            $table->text('allergies')->nullable();
            $table->boolean('keloid_tendency')->nullable();
            $table->text('skincare_products')->nullable();
            $table->string('isotretinoin_use')->nullable();
            $table->boolean('pregnancy_breastfeeding')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmetic_client_profiles');
    }
};
