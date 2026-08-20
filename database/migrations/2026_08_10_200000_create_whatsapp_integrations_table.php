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
        Schema::create('whatsapp_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            // Encrypted at rest (see WhatsAppIntegration's 'encrypted' cast) --
            // this is a live Meta Business API credential, not just a setting.
            $table->text('access_token');
            $table->string('phone_number_id');
            $table->string('business_account_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_integrations');
    }
};
