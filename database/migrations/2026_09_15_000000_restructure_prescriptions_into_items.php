<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prescriptions move from "one row = one medication" to a header +
     * line-item shape (mirrors treatment_charges) so a single prescription
     * can list several medications, added one row at a time from the
     * frontend's "Ekle" table. The three separate dosage/frequency/duration
     * fields collapse into one free-text "1x2x7"-style dosage_instruction
     * per item, matching the doctor's own prescribing shorthand instead of
     * three rigid fields.
     */
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('medication_name');
            $table->string('dosage_instruction')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('prescription_id');
            $table->index('medication_name');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['medication_name', 'dosage', 'frequency', 'duration', 'instructions']);
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('medication_name')->default('');
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->text('instructions')->nullable();
        });

        Schema::dropIfExists('prescription_items');
    }
};
