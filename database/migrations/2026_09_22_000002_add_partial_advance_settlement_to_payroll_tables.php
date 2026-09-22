<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a salary advance be settled in part.
 *
 * Until now settlement was all-or-nothing (settled_by_salary_payment_id set
 * or not), so a period whose earnings could not cover the outstanding
 * advances still marked every one of them settled: the uncovered remainder
 * silently vanished from the payroll report's unsettled_advances figure.
 *
 * salary_advances.settled_amount tracks how much of the advance has actually
 * been recovered, and salary_payments.advance_allocations records exactly how
 * much this payment applied to each advance so deleting the payment can put
 * the advances back the way it found them (settlement is allocated
 * oldest-advance-first, so a single payment can only leave the *last* advance
 * it touched partially settled).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('salary_advances', 'settled_amount')) {
            Schema::table('salary_advances', function (Blueprint $table) {
                $table->decimal('settled_amount', 12, 2)->default(0)->after('amount');
            });

            // Every pre-existing settled advance was settled in full by
            // definition -- the old code had no other option.
            DB::table('salary_advances')
                ->whereNotNull('settled_by_salary_payment_id')
                ->update(['settled_amount' => DB::raw('amount')]);
        }

        if (! Schema::hasColumn('salary_payments', 'advance_allocations')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->json('advance_allocations')->nullable()->after('advances_total');
            });

            // Backfill so deleting an old payment still un-settles exactly
            // the advances it absorbed, full amount each.
            DB::table('salary_advances')
                ->whereNotNull('settled_by_salary_payment_id')
                ->get(['id', 'amount', 'settled_by_salary_payment_id'])
                ->groupBy('settled_by_salary_payment_id')
                ->each(function ($advances, $paymentId) {
                    DB::table('salary_payments')->where('id', $paymentId)->update([
                        'advance_allocations' => json_encode($advances
                            ->map(fn ($advance) => [
                                'salary_advance_id' => (int) $advance->id,
                                'amount' => (float) $advance->amount,
                            ])
                            ->values()
                            ->all()),
                    ]);
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('salary_advances', 'settled_amount')) {
            Schema::table('salary_advances', function (Blueprint $table) {
                $table->dropColumn('settled_amount');
            });
        }

        if (Schema::hasColumn('salary_payments', 'advance_allocations')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                $table->dropColumn('advance_allocations');
            });
        }
    }
};
