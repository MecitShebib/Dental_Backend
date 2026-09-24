<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Vücut Tipi" (athlete / non-athlete) on a body-composition device report --
// a rarely-changing personal attribute, so it lives on the profile alongside
// dietary_type/goal rather than being re-entered on every measurement.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_client_profiles', function (Blueprint $table) {
            $table->string('body_type')->nullable()->after('height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_client_profiles', function (Blueprint $table) {
            $table->dropColumn('body_type');
        });
    }
};
