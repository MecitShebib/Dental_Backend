<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Consent templates turned out to scope by specialty, not by branch
    // (a template applies to one specialty's whole app, regardless of
    // branch) -- replacing the branch_id column added a day earlier.
    public function up(): void
    {
        if (Schema::hasColumn('consent_templates', 'branch_id')) {
            Schema::table('consent_templates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        if (! Schema::hasColumn('consent_templates', 'specialty_id')) {
            Schema::table('consent_templates', function (Blueprint $table) {
                $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });

        Schema::table('consent_templates', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }
};
