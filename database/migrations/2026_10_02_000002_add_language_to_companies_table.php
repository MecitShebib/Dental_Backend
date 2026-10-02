<?php

use App\Models\Company;
use App\Services\SystemMessageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company's own language (chosen in the admin panel), used for the
     * titles of its auto-seeded "System Messages" -- see
     * SystemMessageService::titleFor(). Existing companies get Arabic when
     * they bill in SYP, Turkish otherwise; their untouched default titles
     * are rewritten to match.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('language', 2)->default('tr')->after('currency');
        });

        DB::table('companies')->where('currency', 'SYP')->update(['language' => 'ar']);

        $service = app(SystemMessageService::class);
        Company::withTrashed()->each(fn (Company $company) => $service->retitleForCompany($company));
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
