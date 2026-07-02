<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('treatment_plan')->nullable();
            $table->string('currency_code')->default('SYP');
            $table->decimal('total_services_amount', 12, 2)->default(0);
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('treatment_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('name_tr')->nullable();
            $table->string('color')->nullable();
            $table->decimal('default_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('treatment_record_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_record_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number')->index();
            $table->foreignId('treatment_catalog_id')->constrained('treatment_catalog')->restrictOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['treatment_record_id', 'tooth_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_record_teeth');
        Schema::dropIfExists('treatment_catalog');
        Schema::dropIfExists('treatment_records');
    }
};
