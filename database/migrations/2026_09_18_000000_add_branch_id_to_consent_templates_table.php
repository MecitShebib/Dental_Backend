<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            // Nullable: a template with no branch applies company-wide (every
            // branch), same "null means unscoped" convention already used for
            // clients.branch_id/users.branch_id.
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
