<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            // Optional structured sections rendered under the main body, e.g.
            // [{"heading": "Risks", "body": "..."}, {"heading": "Alternatives", "body": "..."}].
            // A template with no sections just shows the plain body, unchanged.
            $table->json('sections')->nullable()->after('body');
        });

        Schema::table('client_consents', function (Blueprint $table) {
            // Frozen alongside title/body at signing time, same reasoning:
            // editing the template later must never alter an already-signed consent.
            $table->json('sections')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            $table->dropColumn('sections');
        });

        Schema::table('client_consents', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
