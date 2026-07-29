<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ai_treatment_plan_charges', 'treatment_charges');

        Schema::table('treatment_charges', function (Blueprint $table) {
            $table->string('source_type')->default('manual')->after('client_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('treatment_charges', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });

        Schema::rename('treatment_charges', 'ai_treatment_plan_charges');
    }
};
