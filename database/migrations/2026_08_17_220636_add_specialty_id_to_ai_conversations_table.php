<?php

use App\Models\Specialty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ai_conversations.client_id was UNIQUE (one conversation per client, period)
 * -- fine while dental was the only specialty with an AI assistant, but once
 * Gynevaria/Medivaria/Orthovaria/Estevaria each get their own, a shared
 * client with two specialties' AI conversations would collide into one
 * thread. Adds specialty_id and moves the uniqueness to (client_id,
 * specialty_id); every pre-existing row is backfilled to dental, since that
 * was the only specialty this table ever served before now.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_conversations', 'specialty_id')) {
            return;
        }

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
            $table->foreignId('specialty_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');

        if ($dentalId) {
            DB::table('ai_conversations')->whereNull('specialty_id')->update(['specialty_id' => $dentalId]);
        }

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unique(['client_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'specialty_id']);
            $table->dropConstrainedForeignId('specialty_id');
            $table->unique(['client_id']);
        });
    }
};
