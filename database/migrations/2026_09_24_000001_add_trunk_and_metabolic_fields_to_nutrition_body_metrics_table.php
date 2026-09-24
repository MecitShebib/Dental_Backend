<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fields shown on a typical segmental body-composition device report
// (InBody, etc.) that the existing per-limb columns didn't cover: the trunk
// segment's own muscle/fat, plus two whole-body figures (metabolic age,
// daily calorie need/TDEE) distinct from the existing basal_metabolic_rate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_body_metrics', function (Blueprint $table) {
            $table->decimal('trunk_muscle_kg', 4, 1)->nullable()->after('left_leg_fat_percent');
            $table->decimal('trunk_fat_percent', 4, 1)->nullable()->after('trunk_muscle_kg');
            $table->unsignedTinyInteger('metabolic_age')->nullable()->after('trunk_fat_percent');
            $table->unsignedSmallInteger('daily_calorie_need')->nullable()->after('metabolic_age');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_body_metrics', function (Blueprint $table) {
            $table->dropColumn(['trunk_muscle_kg', 'trunk_fat_percent', 'metabolic_age', 'daily_calorie_need']);
        });
    }
};
