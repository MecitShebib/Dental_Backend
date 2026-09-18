<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'key', 'channel', 'language']);
            $table->unique(['company_id', 'branch_id', 'key', 'channel', 'language']);
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'branch_id', 'key', 'channel', 'language']);
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->unique(['company_id', 'key', 'channel', 'language']);
        });
    }
};
