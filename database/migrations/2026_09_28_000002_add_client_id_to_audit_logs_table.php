<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Denormalized (not a join through auditable_id, which is
            // polymorphic across ~40 model types) so "show me every action on
            // this patient" is one indexed where() instead of N type-specific
            // joins. No foreign key -- same deliberate choice as auditable_id
            // (see the 2026-09-05 migration): a client may later be
            // anonymized/erased while its access history must be kept.
            $table->unsignedBigInteger('client_id')->nullable()->after('auditable_id');
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropColumn('client_id');
        });
    }
};
