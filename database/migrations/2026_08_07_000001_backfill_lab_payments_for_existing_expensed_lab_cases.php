<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lab cases created before the lab_payments ledger existed had their entire
 * lab_cost auto-expensed the moment it was set (LabCaseCostSyncService,
 * since retired). Represent that historical lump sum as a single "fully
 * paid" LabPayment pointing at the SAME already-posted Expense, rather than
 * creating a new one -- so remaining_balance reads 0 for these cases without
 * double-counting the fund impact that already happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        $labCases = DB::table('lab_cases')
            ->whereNotNull('expense_id')
            ->whereNotNull('lab_cost')
            ->get(['id', 'lab_cost', 'expense_id', 'sent_date', 'created_by']);

        foreach ($labCases as $labCase) {
            $alreadyMigrated = DB::table('lab_payments')->where('expense_id', $labCase->expense_id)->exists();

            if ($alreadyMigrated) {
                continue;
            }

            DB::table('lab_payments')->insert([
                'uuid' => (string) Str::uuid(),
                'lab_case_id' => $labCase->id,
                'payment_date' => $labCase->sent_date,
                'amount' => $labCase->lab_cost,
                'payment_method' => 'cash',
                'expense_id' => $labCase->expense_id,
                'notes' => 'Migrated from the legacy lump-sum lab expense recorded when this case was first costed.',
                'created_by' => $labCase->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('lab_payments')
            ->where('notes', 'Migrated from the legacy lump-sum lab expense recorded when this case was first costed.')
            ->delete();
    }
};
