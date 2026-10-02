<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every company now has exactly one currency (SYP/TRY/USD), chosen
     * mandatorily at creation in the admin panel -- the whole app (pricing,
     * accounting, everything formatCurrency touches) renders in that one
     * currency from then on. Defaults existing rows to TRY, matching the
     * hardcoded "TL" label formatCurrency showed everywhere before this.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('currency', 3)->default('TRY')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
