<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_booking_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('mobile')->index();
            $table->string('otp_code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('reference')->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'mobile', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_booking_otps');
    }
};
