<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lab_cases', 'expense_id')) {
            return;
        }

        Schema::table('lab_cases', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('lab_cost')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });
    }
};
