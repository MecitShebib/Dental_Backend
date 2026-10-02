<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PDFs of generated patient documents (treatment plan, prescription,
     * invoice, consent, lab results, nutrition plan) shared with the patient
     * over WhatsApp as a link. Kept for SharedDocument::LIFETIME_DAYS, then
     * deleted (file + row) by documents:purge-expired.
     */
    public function up(): void
    {
        // A short-lived earlier version (2026_09_27_000001, since removed)
        // created a `shared_documents` table of HTML snapshots on some
        // servers. Its rows were temporary share links to a route that no
        // longer exists, so an old-shaped table is simply replaced; a table
        // that already has the new shape is left alone.
        if (Schema::hasTable('shared_documents')) {
            if (Schema::hasColumn('shared_documents', 'path')) {
                return;
            }

            Schema::drop('shared_documents');
        }

        Schema::create('shared_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('filename');
            $table->string('path');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_documents');
    }
};
