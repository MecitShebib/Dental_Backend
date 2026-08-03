<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            // Plain incrementing integer, unique per company; formatted for
            // display (e.g. "INV-000123") in InvoiceResource -- kept numeric
            // here so computing "next number" is a simple locked max()+1.
            $table->unsignedInteger('invoice_number');
            $table->decimal('amount', 12, 2);
            $table->date('issued_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
