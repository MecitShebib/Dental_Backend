<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-healing: an earlier version of this migration named its FK
        // constraint too long for MySQL (>64 chars), which failed that ALTER
        // but left the CREATE TABLE committed (MySQL DDL isn't transactional)
        // without ever recording this migration as run -- so a retry hits
        // "table already exists" indefinitely. Safe to drop unconditionally
        // since this table is brand new (never part of a completed batch).
        Schema::dropIfExists('treatment_charge_inventory_consumptions');

        // Tracks "how much of inventory item Z is currently consumed because
        // of source (visit #12, appointment #7, ...)" as a live current-state
        // row per (source, item) pair -- not a ledger. InventoryService diffs
        // against this on every TreatmentChargeService::syncItems() re-sync
        // (a full delete-then-recreate of a source's charges) to work out
        // exactly how much stock to restock or further consume, and records
        // the actual movement as ordinary inventory_transactions rows.
        Schema::create('treatment_charge_inventory_consumptions', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('inventory_item_id');
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            // Explicit short names -- the table name is long enough that
            // Laravel's auto-generated names (both of these) exceed MySQL's
            // 64-char identifier limit.
            $table->foreign('inventory_item_id', 'charge_inv_consumption_item_fk')
                ->references('id')->on('inventory_items')->cascadeOnDelete();
            $table->unique(['source_type', 'source_id', 'inventory_item_id'], 'charge_inv_consumption_unique');
        });

        if (! Schema::hasColumn('inventory_transactions', 'is_auto_consumption')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                // Set only on transactions InventoryService created automatically
                // from a treatment charge sync -- lets the low-stock-alert reason
                // and reporting distinguish "the clinic recorded this by hand"
                // from "this happened because a procedure was billed."
                $table->boolean('is_auto_consumption')->default(false)->after('reason');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_charge_inventory_consumptions');

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('is_auto_consumption');
        });
    }
};
