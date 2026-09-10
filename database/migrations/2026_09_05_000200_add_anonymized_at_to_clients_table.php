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
        Schema::table('clients', function (Blueprint $table) {
            // Set by ClientErasureService::anonymize() when a KVKK m.7
            // erasure request is fulfilled. Distinct from deleted_at
            // (SoftDeletes, also set at the same time): deleted_at alone
            // means "hidden, still restorable with real data"; a non-null
            // anonymized_at means the identifying fields were irreversibly
            // masked and there is nothing left to restore.
            $table->timestamp('anonymized_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
