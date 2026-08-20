<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-healing: an earlier version of this migration named its unique
        // constraint too long for MySQL (>64 chars), which failed the ALTER
        // but left the CREATE TABLE committed (MySQL DDL isn't transactional)
        // without ever recording this migration as run -- so a retry hits
        // "table already exists" indefinitely. Since this table is brand new
        // (never part of a completed migration batch), dropping any leftover
        // copy first is safe on every environment, including ones deployed
        // via a web script with no shell access to run a manual fix.
        Schema::dropIfExists('treatment_catalog_inventory_links');

        // Lets an admin declare "performing procedure X consumes Y units of
        // inventory item Z" so InventoryService can auto-decrement stock when
        // that procedure is billed. One catalog entry can consume several
        // different inventory items (e.g. a filling consumes both composite
        // material and an anesthetic cartridge).
        Schema::create('treatment_catalog_inventory_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_catalog_id')->constrained('treatment_catalog')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_per_use', 10, 2)->default(1);
            $table->timestamps();

            // Default auto-generated name exceeds MySQL's 64-char identifier limit.
            $table->unique(['treatment_catalog_id', 'inventory_item_id'], 'catalog_inventory_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_catalog_inventory_links');
    }
};
