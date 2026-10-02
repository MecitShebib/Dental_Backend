<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_surgery_followups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('followup_date');
            $table->foreignId('operation_id')->nullable()->constrained('general_surgery_operations')->nullOnDelete();
            $table->string('wound_status')->nullable();
            $table->date('drain_removed_at')->nullable();
            $table->date('sutures_removed_at')->nullable();
            $table->text('complications')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'followup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_surgery_followups');
    }
};
