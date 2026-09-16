<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->longText('diet_plan')->nullable()->after('summary');
            $table->longText('exercise_plan')->nullable()->after('diet_plan');
        });
    }

    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn(['diet_plan', 'exercise_plan']);
        });
    }
};
