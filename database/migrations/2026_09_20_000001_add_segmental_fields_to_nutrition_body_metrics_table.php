<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bilateral (right/left) arm/leg muscle+fat readings, as shown on a
// segmental body-composition device report (InBody, etc.) -- the existing
// columns above (body_fat_percent, muscle_mass_kg, ...) stay as the
// whole-body figures; these are the per-limb breakdown shown alongside the
// body-silhouette diagram (see bodySilhouetteDocument.js).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_body_metrics', function (Blueprint $table) {
            $table->decimal('right_arm_muscle_kg', 4, 1)->nullable()->after('hip_cm');
            $table->decimal('left_arm_muscle_kg', 4, 1)->nullable()->after('right_arm_muscle_kg');
            $table->decimal('right_arm_fat_percent', 4, 1)->nullable()->after('left_arm_muscle_kg');
            $table->decimal('left_arm_fat_percent', 4, 1)->nullable()->after('right_arm_fat_percent');
            $table->decimal('right_leg_muscle_kg', 4, 1)->nullable()->after('left_arm_fat_percent');
            $table->decimal('left_leg_muscle_kg', 4, 1)->nullable()->after('right_leg_muscle_kg');
            $table->decimal('right_leg_fat_percent', 4, 1)->nullable()->after('left_leg_muscle_kg');
            $table->decimal('left_leg_fat_percent', 4, 1)->nullable()->after('right_leg_fat_percent');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_body_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'right_arm_muscle_kg', 'left_arm_muscle_kg',
                'right_arm_fat_percent', 'left_arm_fat_percent',
                'right_leg_muscle_kg', 'left_leg_muscle_kg',
                'right_leg_fat_percent', 'left_leg_fat_percent',
            ]);
        });
    }
};
