<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_surgery_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('operated_at');
            $table->string('operation_name');
            $table->integer('duration_minutes')->nullable();
            $table->string('anesthesia_type')->nullable();
            $table->text('surgeon_notes')->nullable();
            $table->text('complications')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'operated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_surgery_operations');
    }
};
