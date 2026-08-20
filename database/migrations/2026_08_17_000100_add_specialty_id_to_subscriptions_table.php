<?php

use App\Models\Specialty;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        // Every existing subscription predates multi-specialty support and is
        // a dental (Dentavaria) subscription -- backfill rather than leave
        // null, since a company's active-specialty list is derived by
        // grouping subscriptions on this column.
        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');
        if ($dentalId) {
            Subscription::query()->whereNull('specialty_id')->update(['specialty_id' => $dentalId]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
