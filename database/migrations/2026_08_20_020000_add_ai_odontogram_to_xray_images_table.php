<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_images', function (Blueprint $table) {
            $table->json('ai_odontogram_status')->nullable()->after('notes');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_odontogram_status');
        });
    }

    public function down(): void
    {
        Schema::table('xray_images', function (Blueprint $table) {
            $table->dropColumn(['ai_odontogram_status', 'ai_analyzed_at']);
        });
    }
};
