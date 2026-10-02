<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured (table-shaped) diet / exercise plans -- see
 * App\Specialties\Nutrition\NutritionPlanStructure. The existing
 * diet_plan / exercise_plan text columns stay and keep a readable rendering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->json('diet_plan_data')->nullable()->after('exercise_plan');
            $table->json('exercise_plan_data')->nullable()->after('diet_plan_data');
        });
    }

    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn(['diet_plan_data', 'exercise_plan_data']);
        });
    }
};
