<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_images', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('xray_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
