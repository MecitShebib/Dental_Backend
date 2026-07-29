<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The app now prices everything in Turkish Lira (see App\Services\TreatmentRecordService
    // and App\Http\Controllers\Api\ClientTreatmentRecordController, which already set
    // 'TRY' for any new record). This just backfills any existing rows that were created
    // under the old 'SYP' default -- the column's own DB-level default is left as-is since
    // the app never inserts a treatment_records row without setting currency_code explicitly.
    public function up(): void
    {
        DB::table('treatment_records')->where('currency_code', 'SYP')->update(['currency_code' => 'TRY']);
    }

    public function down(): void
    {
        DB::table('treatment_records')->where('currency_code', 'TRY')->update(['currency_code' => 'SYP']);
    }
};
