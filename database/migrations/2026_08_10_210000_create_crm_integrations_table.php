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
        Schema::create('crm_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            // 'provider' is here for future non-Zoho CRMs; only 'zoho' is
            // implemented today (ZohoCrmService).
            $table->string('provider')->default('zoho');
            $table->string('client_id');
            $table->text('client_secret');
            $table->text('refresh_token');
            $table->string('accounts_base_url')->nullable();
            $table->string('api_base_url')->nullable();
            // Cached short-lived access token so every push doesn't need a
            // fresh OAuth refresh round-trip -- see ZohoCrmService::accessToken().
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
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
        Schema::dropIfExists('crm_integrations');
    }
};
