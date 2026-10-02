<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosmetic_procedure_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('performed_at');
            $table->string('procedure_type');
            $table->string('product_name')->nullable();
            $table->string('lot_number')->nullable();
            $table->decimal('amount', 8, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('treatment_area')->nullable();
            $table->string('before_photo_path')->nullable();
            $table->string('before_photo_original_filename')->nullable();
            $table->string('after_photo_path')->nullable();
            $table->string('after_photo_original_filename')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmetic_procedure_logs');
    }
};
