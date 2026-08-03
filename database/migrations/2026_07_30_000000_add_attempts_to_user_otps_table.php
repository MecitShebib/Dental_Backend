<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_otps', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('user_otps', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
