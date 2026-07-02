<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->longText('planned_summary')->nullable()->after('notes');
            $table->longText('planned_notes')->nullable()->after('planned_summary');
            $table->string('planned_image_path')->nullable()->after('planned_notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['planned_summary', 'planned_notes', 'planned_image_path']);
        });
    }
};
