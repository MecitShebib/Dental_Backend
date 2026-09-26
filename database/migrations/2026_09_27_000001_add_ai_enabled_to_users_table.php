<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-doctor kill switch for the AI assistant, settable by a company's own
// System Manager or the platform admin panel. Defaults true so every
// existing doctor keeps the access they already have today; only doctors
// see/are affected by this at all -- assertCanUseAiAssistant() already lets
// a non-doctor System Manager through regardless of this flag.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'ai_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(true)->after('is_doctor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_enabled');
        });
    }
};
