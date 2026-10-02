<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which optional features a subscription includes (Subscription::FEATURES
     * keys). NULL = the row predates this column and keeps every feature, so
     * existing clinics lose nothing until an admin edits their subscription.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('features')->nullable()->after('ai_tokens_used');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
