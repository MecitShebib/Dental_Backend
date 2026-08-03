<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_advances')) {
            return;
        }

        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('advance_date')->index();
            $table->text('note')->nullable();
            // No FK constraint: salary_payments is created in a later migration,
            // and this mirrors how other ledger tables here (e.g. treatment_charges'
            // source_id) reference rows without a hard DB constraint.
            $table->unsignedBigInteger('settled_by_salary_payment_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
