<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dicom_series', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dicom_study_id')->constrained()->cascadeOnDelete();
            $table->string('series_uid')->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->unsignedInteger('columns')->nullable();
            $table->unsignedInteger('slice_count')->default(0);
            $table->decimal('pixel_spacing_x', 8, 4)->nullable();
            $table->decimal('pixel_spacing_y', 8, 4)->nullable();
            $table->decimal('slice_thickness', 8, 4)->nullable();
            $table->string('orientation')->nullable();
            $table->string('storage_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dicom_series');
    }
};
