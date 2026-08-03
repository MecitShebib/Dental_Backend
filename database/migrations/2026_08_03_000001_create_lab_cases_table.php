<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_cases')) {
            return;
        }

        Schema::create('lab_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lab_partner_id')->nullable()->constrained('lab_partners')->nullOnDelete();
            // Nullable, manually attached to an existing fitting/delivery
            // appointment (or left unset) -- see AppointmentLinkedToLabCaseTest.
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('work_type');
            $table->json('teeth')->nullable();
            $table->string('material')->nullable();
            $table->string('shade')->nullable();
            $table->string('status')->default('sent');
            $table->date('sent_date');
            $table->date('expected_return_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('lab_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('client_id');
            $table->index('doctor_id');
            $table->index('lab_partner_id');
            $table->index('appointment_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_cases');
    }
};
