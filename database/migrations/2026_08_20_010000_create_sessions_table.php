<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SESSION_DRIVER=database has been the configured driver all along (see
 * .env.example), but this table's own migration was never committed --
 * production almost certainly already has it out-of-band (admin panel login
 * has worked all session), hence the hasTable() guard rather than a bare
 * create. Needed now so Admin\AuthController::login() can look up and
 * invalidate a user's other sessions (single-session-per-account login).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
