<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pins one language per AI conversation. The chat system prompts used to say
 * only "reply in the same language the doctor is writing in", leaving the
 * model to re-infer the conversation's language from scratch on every turn --
 * which it sometimes got wrong, flipping a fully English thread into Arabic
 * mid-conversation (QA audit 2026-09-22, section 2.2; reproduced in both
 * dental and nutrition, i.e. it is the shared prompt pattern at fault).
 * Resolved once, when the conversation row is first created
 * (AiConversationService::resolveConversationLanguage()), then stated
 * explicitly in every system prompt from then on.
 *
 * Nullable rather than defaulted: rows created before this column existed are
 * back-filled lazily from their own opening message the next time the doctor
 * writes in them, and a null here is what marks a row as "not resolved yet".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_conversations', 'language')) {
            return;
        }

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('language', 5)->nullable()->after('specialty_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
