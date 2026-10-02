<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sale can now go through with no patient at all (walk-in retail,
     * revenue posted straight to the fund), and a client-linked sale can be
     * marked paid-on-the-spot (also posted to the fund) instead of always
     * becoming patient debt -- see InventorySaleService::create().
     */
    public function up(): void
    {
        Schema::table('inventory_sales', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
            $table->boolean('is_paid')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_sales', function (Blueprint $table) {
            $table->dropColumn('is_paid');
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};
