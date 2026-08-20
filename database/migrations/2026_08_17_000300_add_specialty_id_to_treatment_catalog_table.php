<?php

use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_catalog', function (Blueprint $table) {
            $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        // Every existing row (company-scope services and odontogram-scope
        // procedures alike) was seeded by TreatmentCatalogSeeder, which is
        // entirely dental (Dentavaria) content today.
        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');
        if ($dentalId) {
            TreatmentCatalog::query()->whereNull('specialty_id')->update(['specialty_id' => $dentalId]);
        }
    }

    public function down(): void
    {
        Schema::table('treatment_catalog', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
