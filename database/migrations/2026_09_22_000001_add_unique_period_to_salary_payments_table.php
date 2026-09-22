<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "one salary payment per employee per period" a database rule instead
 * of only an application-level exists() check, which two concurrent requests
 * could both pass before either had inserted.
 *
 * void_marker exists because salary_payments is soft-deleted and the
 * documented way to correct an amount is "delete the payment and record it
 * again". A plain unique index over the four period columns would make that
 * permanent (the trashed row keeps occupying its period forever), and adding
 * deleted_at to the index would not help: NULL values count as distinct in a
 * unique index on every driver this app supports, so two *live* rows -- the
 * case we actually need to block -- would still slip through. void_marker is
 * 0 on every live row (so live duplicates collide) and is set to the row's
 * own id when the payment is deleted, releasing the period again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('salary_payments', 'void_marker')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('void_marker')->default(0)->after('period_month');
            });
        }

        // Already-deleted payments release their period.
        DB::table('salary_payments')
            ->whereNotNull('deleted_at')
            ->where('void_marker', 0)
            ->update(['void_marker' => DB::raw('id')]);

        // Any live duplicate the old race already produced would make the
        // index uncreatable. Rather than destroy accounting rows, push the
        // later ones out of the unique slot (earliest id keeps the period);
        // they stay fully visible in the payroll ledger for a human to
        // reconcile and delete.
        $duplicates = DB::table('salary_payments')
            ->selectRaw('company_id, user_id, period_year, period_month, MIN(id) as keep_id')
            ->whereNull('deleted_at')
            ->groupBy('company_id', 'user_id', 'period_year', 'period_month')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            DB::table('salary_payments')
                ->whereNull('deleted_at')
                ->where('company_id', $group->company_id)
                ->where('user_id', $group->user_id)
                ->where('period_year', $group->period_year)
                ->where('period_month', $group->period_month)
                ->where('id', '!=', $group->keep_id)
                ->update(['void_marker' => DB::raw('id')]);
        }

        // Explicit short name: the auto-generated one would exceed MySQL's
        // 64-character identifier limit, same reason as the plain index this
        // table already carries.
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'user_id', 'period_year', 'period_month', 'void_marker'],
                'salary_payments_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropUnique('salary_payments_period_unique');
        });

        if (Schema::hasColumn('salary_payments', 'void_marker')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->dropColumn('void_marker');
            });
        }
    }
};
