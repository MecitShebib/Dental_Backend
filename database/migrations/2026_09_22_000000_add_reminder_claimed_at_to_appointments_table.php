<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits the reminder's "someone is working on this" marker away from its
     * "this was actually delivered" marker.
     *
     * Before this, `reminder_sent_at` did both jobs: the command set it up
     * front to stop two overlapping runs dispatching the same reminder twice,
     * and candidates() then skipped anything with it set. So a send that
     * failed every retry left the appointment marked as reminded forever --
     * the message was silently, permanently lost with nothing to pick it up
     * again. `reminder_claimed_at` now carries the de-duplication role (and
     * goes stale after an hour so a dead attempt can be retried), while
     * `reminder_sent_at` is only written once a channel has actually
     * accepted the message.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminder_claimed_at')->nullable()->after('reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reminder_claimed_at');
        });
    }
};
