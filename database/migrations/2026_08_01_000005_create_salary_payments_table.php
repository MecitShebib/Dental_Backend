<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guards against a request that created the table but was interrupted
        // (timeout/double-fire) before Laravel could record it as migrated --
        // seen in production for this exact migration.
        if (Schema::hasTable('salary_payments')) {
            return;
        }

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('advances_total', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->date('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            // Explicit short name: the auto-generated one
            // ("salary_payments_company_id_user_id_period_year_period_month_index",
            // 65 chars) exceeds MySQL's 64-char identifier limit.
            $table->index(['company_id', 'user_id', 'period_year', 'period_month'], 'salary_payments_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
