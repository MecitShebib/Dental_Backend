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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('unit')->default('piece');
            $table->decimal('quantity_on_hand', 10, 2)->default(0);
            // Null threshold/quantity = reorder alerts disabled for this item.
            $table->decimal('reorder_threshold', 10, 2)->nullable();
            $table->decimal('reorder_quantity', 10, 2)->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            // Edge-triggered: only re-alert after the level goes back above
            // threshold and drops again -- see InventoryService::recordTransaction().
            $table->timestamp('reorder_alert_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
