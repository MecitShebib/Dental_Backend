<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public booking codes can go by email (MOBILE_OTP_CHANNEL=email), where the
 * phone number is optional -- so a challenge is keyed by email or mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_booking_otps', function (Blueprint $table) {
            $table->string('email')->nullable()->after('mobile')->index();
            $table->string('mobile')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('public_booking_otps', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn('email');
        });
    }
};
