<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which version of the Terms of Service (LegalContent::TERMS_VERSION) each
 * user personally accepted, and when -- the record that makes "one account =
 * one named person" enforceable: every action taken under the account is
 * attributed to the person who accepted these terms. Null = never accepted,
 * so every existing user is asked once on their next request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_accepted_version', 32)->nullable()->after('last_login_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_accepted_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_version', 'terms_accepted_at']);
        });
    }
};
