<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which specialties a branch actually operates -- a branch with no rows
    // here at all is unrestricted (visible from every specialty), same
    // "empty/null means everywhere" rule used throughout this feature; a
    // branch with at least one row here is only ever offered while inside
    // one of its listed specialties.
    public function up(): void
    {
        Schema::create('branch_specialty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->unique(['branch_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_specialty');
    }
};
