<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physiotherapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->integer('session_number')->nullable();
            $table->integer('pain_vas')->nullable();
            $table->json('rom_measurements')->nullable();
            $table->json('muscle_strength')->nullable();
            $table->json('modalities')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physiotherapy_sessions');
    }
};
