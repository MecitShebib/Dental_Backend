<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_charges', function (Blueprint $table) {
            // Nullable and only ever populated when a line item was added by
            // picking a priced catalog entry (see ChargeItemsEditor on the
            // frontend) -- a free-text manual amount leaves this null. Lets
            // InventoryService know which procedure a charge line represents,
            // so it can auto-consume any inventory linked to that procedure.
            $table->foreignId('treatment_catalog_id')->nullable()->after('source_id')
                ->constrained('treatment_catalog')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_catalog_id');
        });
    }
};
