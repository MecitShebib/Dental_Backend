<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('salary_payments', 'commission_amount')) {
            return;
        }

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->decimal('treatment_revenue', 12, 2)->default(0)->after('base_salary');
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('treatment_revenue');
            $table->decimal('commission_amount', 12, 2)->default(0)->after('commission_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropColumn(['treatment_revenue', 'commission_percentage', 'commission_amount']);
        });
    }
};
