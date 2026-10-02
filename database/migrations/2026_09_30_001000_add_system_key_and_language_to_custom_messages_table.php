<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tags the 4 auto-seeded "System Messages" entries (appointment_reminder/
     * patient_recall/booking_confirmation/satisfaction_survey, one per
     * language) so SystemMessageService can find the current, possibly
     * staff-edited wording at send time -- both columns are null for an
     * ordinary custom message a staff member wrote themselves. See
     * SystemMessageService::seedForCompanySpecialty()/bodyFor().
     */
    public function up(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            $table->string('system_key')->nullable()->after('message_group_id');
            $table->string('language', 2)->nullable()->after('system_key');
        });
    }

    public function down(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            $table->dropColumn(['system_key', 'language']);
        });
    }
};
