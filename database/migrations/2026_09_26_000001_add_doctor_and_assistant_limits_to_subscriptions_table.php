<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate seat caps per user type (e.g. Essentials = 3 doctors + 3
     * assistant users). Nullable = no separate cap for that type -- existing
     * subscriptions keep behaving exactly as before, gated only by max_users.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('max_doctors')->nullable()->after('max_users');
            $table->unsignedInteger('max_assistants')->nullable()->after('max_doctors');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['max_doctors', 'max_assistants']);
        });
    }
};
