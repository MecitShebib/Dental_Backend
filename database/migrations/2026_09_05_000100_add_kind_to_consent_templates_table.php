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
        Schema::table('consent_templates', function (Blueprint $table) {
            // 'clinical' = the pre-existing treatment-consent use case
            // (signature pad for a procedure). 'kvkk_disclosure'/
            // 'kvkk_explicit_consent' are the KVKK-specific Aydınlatma
            // Metni / Açık Rıza Beyanı seeded per company by
            // KvkkConsentTemplateSeeder -- see the KVKK compliance plan,
            // docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md, Görev 2.2.
            $table->string('kind')->default('clinical')->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
