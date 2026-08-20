<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staff-facing client creation (StoreClientRequest) still requires
     * gender -- this only loosens the DB constraint so the public online
     * booking form, which deliberately only asks for name + phone to keep
     * friction low, can create a client without it.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable(false)->change();
        });
    }
};
