<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hematology_blood_counts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('measured_at');
            $table->decimal('hb', 4, 1)->nullable();
            $table->decimal('hct', 4, 1)->nullable();
            $table->decimal('wbc', 6, 2)->nullable();
            $table->integer('plt')->nullable();
            $table->decimal('mcv', 5, 1)->nullable();
            $table->decimal('ferritin', 8, 1)->nullable();
            $table->decimal('inr', 4, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hematology_blood_counts');
    }
};
