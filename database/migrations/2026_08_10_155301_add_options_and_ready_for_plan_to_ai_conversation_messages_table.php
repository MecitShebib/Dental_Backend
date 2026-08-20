<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_conversation_messages', function (Blueprint $table) {
            $table->json('options')->nullable()->after('image_urls');
            $table->boolean('ready_for_plan')->default(false)->after('options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversation_messages', function (Blueprint $table) {
            $table->dropColumn(['options', 'ready_for_plan']);
        });
    }
};
