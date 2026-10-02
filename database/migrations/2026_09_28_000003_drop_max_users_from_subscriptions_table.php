<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seats are capped per type only (max_doctors / max_assistants, see
 * CompanyUserLimitService) -- the overall max_users cap is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->nullable()->after('ends_at');
        });
    }
};
