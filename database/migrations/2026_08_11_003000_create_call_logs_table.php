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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Nullable: a call from/to a number that doesn't match any known
            // client -- see CallLogService::log() for the auto-match-by-phone.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number');
            $table->string('direction');
            $table->string('status');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
