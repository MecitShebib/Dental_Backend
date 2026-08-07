<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xray_images')) {
            return;
        }

        Schema::create('xray_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Nullable: an image lands in the shared company gallery unlinked
            // (e.g. freshly posted by an X-ray machine via API token, which
            // has no notion of "client") until a staff member attaches it to
            // the right patient from the picker.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path');
            $table->string('original_filename')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_images');
    }
};
