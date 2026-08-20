<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('recording_url')->nullable()->after('duration_seconds');
            // Idempotency key for webhook-created rows (a telephony provider's
            // own call id) -- manual UI-created rows leave this null. Unique so
            // a retried webhook delivery can't create a duplicate row.
            $table->string('external_id')->nullable()->unique()->after('recording_url');
            $table->timestamp('followed_up_at')->nullable()->after('notes');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('call_webhook_secret')->nullable()->after('booking_slug');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn(['recording_url', 'external_id', 'followed_up_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('call_webhook_secret');
        });
    }
};
