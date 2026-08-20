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
        Schema::table('companies', function (Blueprint $table) {
            // Null = use services.patient_recall.default_interval_days. 0 = recalls
            // explicitly disabled for this company. A positive number overrides the
            // default with a company-specific interval.
            $table->unsignedSmallInteger('recall_interval_days')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('recall_interval_days');
        });
    }
};
