<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Message groups belong to one specialty (a nutrition clinic's "Diabetes
     * patients" group shouldn't show up in the dental app). NULL = a group
     * made before this column existed, shown in every specialty.
     */
    public function up(): void
    {
        Schema::table('message_groups', function (Blueprint $table) {
            $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('message_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
