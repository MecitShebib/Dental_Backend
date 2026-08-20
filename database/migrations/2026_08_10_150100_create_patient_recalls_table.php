<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_recalls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // The visit whose age made the client due; one recall per visit
            // cycle keeps the cron idempotent without a separate "last sent"
            // window calculation, and lets a client be recalled again after
            // their next completed visit.
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_recalls');
    }
};
