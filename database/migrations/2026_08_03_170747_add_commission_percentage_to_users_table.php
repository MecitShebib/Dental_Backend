<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'commission_percentage')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('commission_percentage');
        });
    }
};
