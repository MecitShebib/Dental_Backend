<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            // All optional sub-ratings alongside the required overall `rating` --
            // a patient can always just rate the overall experience and skip these.
            $table->unsignedTinyInteger('wait_time_rating')->nullable()->after('rating');
            $table->unsignedTinyInteger('staff_rating')->nullable()->after('wait_time_rating');
            $table->unsignedTinyInteger('cleanliness_rating')->nullable()->after('staff_rating');
        });
    }

    public function down(): void
    {
        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            $table->dropColumn(['wait_time_rating', 'staff_rating', 'cleanliness_rating']);
        });
    }
};
