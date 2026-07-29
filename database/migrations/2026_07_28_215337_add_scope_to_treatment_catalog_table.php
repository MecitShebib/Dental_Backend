<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_catalog', function (Blueprint $table) {
            // "company": the simple, manually-managed price list shown in the admin
            // Settings > Pricing screen (SettingsDialog.jsx). "odontogram": the
            // per-procedure catalog covering every condition/treatment the V2
            // odontogram widget can select, keyed by "{category}:{value}" and used
            // only for computing a visit/appointment/AI-plan's cost -- kept out of
            // the company-facing product list so that screen isn't cluttered with
            // internal widget codes like "indicators:bruxismWear".
            $table->string('scope')->default('company')->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_catalog', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
