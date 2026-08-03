<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capital_transactions')) {
            return;
        }

        Schema::create('capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('party_name')->nullable();
            $table->date('transaction_date')->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_transactions');
    }
};
