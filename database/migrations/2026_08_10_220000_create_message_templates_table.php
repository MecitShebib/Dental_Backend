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
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // See App\Support\MessageTemplateDefaults for the full key/channel
            // matrix and the fallback text used when no row exists here.
            $table->string('key');
            $table->string('channel');
            $table->string('language');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'key', 'channel', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
