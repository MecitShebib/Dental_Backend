<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_catalog', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_catalog', function (Blueprint $table) {
            $table->string('color')->nullable();
        });
    }
};
