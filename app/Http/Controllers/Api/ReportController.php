<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySaleItem;
use App\Models\LabPartner;
use App\Models\LabPayment;
use App\Models\SalaryAdvance;
use App\Models\SalaryPayment;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use AuthorizesAccounting;

    /**
     * Every client whose treatment_charges total exceeds what they've paid,
     * highest balance first -- same remaining_amount formula as
     * ClientFinancialSummaryService, just across every client at once
     * instead of one at a time.
     */
    public function patientDebts(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
        ]);

        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $rows = Client::query()
            // A client with no branch_id assigned yet (pre-dates branch scoping)
            // stays visible from every branch rather than silently disappearing.
            ->when($request->branch_id, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $request->branch_id)->orWhereNull('branch_id')))
            // "this doctor's/specialty's patients" -- same ownership model
            // ClientQueryService uses for the Patients list (primary_doctor_id
            // on the client's specialty enrollment, not just "has ever had a
            // visit with").
            ->when($request->doctor_id, fn ($q) => $q->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('primary_doctor_id', $request->doctor_id)
            ))
            ->when($specialtyId, fn ($q) => $q->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $specialtyId)
            ))
            ->withSum('treatmentCharges as total_services', 'amount')
            ->withSum('payments as total_paid', 'amount')
            ->with('specialtyRecords.specialty')
            ->get()
            ->map(function (Client $client) use ($specialtyId) {
                $totalServices = round((float) ($client->total_services ?? 0), 2);
                $totalPaid = round((float) ($client->total_paid ?? 0), 2);

                // Which specialty's own Client Details page "View" should open
                // -- the one this report was filtered to, when given, since
                // every row is already scoped to it; otherwise whichever
                // specialty this client is enrolled in (first one, for the
                // rare case of more than one -- see ClientSpecialtyRecord).
                $specialtyRecord = $specialtyId
                    ? $client->specialtyRecords->firstWhere('specialty_id', $specialtyId)
                    : $client->specialtyRecords->first();

                return [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone,
                    'total_services_amount' => $totalServices,
                    'total_paid_amount' => $totalPaid,
                    'remaining_amount' => round($totalServices - $totalPaid, 2),
                    'specialty_key' => $specialtyRecord?->specialty?->key,
                ];
            })
            ->filter(fn (array $row) => $row['remaining_amount'] > 0)
            ->sortByDesc('remaining_amount')
            ->values();

        return $this->success($rows);
    }

    /**
     * Every lab partner the company still owes money to: sum of every case's
     * lab_cost minus every recorded LabPayment against those same cases,
     * highest balance first.
     */
    public function labDebts(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
        ]);

        $branchId = $request->branch_id;
        $doctorId = $request->doctor_id;
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $rows = LabPartner::query()
            ->with(['labCases' => function ($query) use ($branchId, $doctorId, $specialtyId) {
                $query->whereNotNull('lab_cost')
                    ->when($branchId, fn ($q) => $q->whereHas('client', fn ($cq) => $cq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id'))))
                    ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
                    ->when($specialtyId, fn ($q) => $q->whereHas('doctor', fn ($dq) => $dq->where('specialty_id', $specialtyId)));
            }])
            ->get()
            ->map(function (LabPartner $labPartner) {
                $labCaseIds = $labPartner->labCases->pluck('id');
                $totalCost = round((float) $labPartner->labCases->sum('lab_cost'), 2);
                $totalPaid = round((float) LabPayment::query()->whereIn('lab_case_id', $labCaseIds)->sum('amount'), 2);

                return [
                    'lab_partner_id' => $labPartner->id,
                    'lab_partner_name' => $labPartner->name,
                    'total_lab_cost' => $totalCost,
                    'total_paid' => $totalPaid,
                    'remaining_balance' => round($totalCost - $totalPaid, 2),
                ];
            })
            ->filter(fn (array $row) => $row['remaining_balance'] > 0)
            ->sortByDesc('remaining_balance')
            ->values();

        return $this->success($rows);
    }

    /**
     * Per-employee payroll snapshot for a given month: what they were paid
     * (base + commission) that period, and what's still owed against them
     * (unsettled salary advances) regardless of period -- an HR-facing
     * complement to the payroll/salary-payments ledger, which is per-record
     * rather than per-employee-per-month.
     */
    public function payrollSummary(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
        ]);

        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $rows = User::query()
            ->with('roles')
            ->where('status', 'active')
            // A user with no branch_id assigned yet (pre-dates branch scoping)
            // stays visible from every branch rather than silently disappearing.
            ->when($request->branch_id, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $request->branch_id)->orWhereNull('branch_id')))
            ->when($request->doctor_id, fn ($q) => $q->where('id', $request->doctor_id))
            // Strict per user request: a specialty filter shows only staff
            // actually assigned to that specialty (via specialty_id), doctor
            // or not -- previously any non-doctor with no specialty_id at
            // all (accountants, secretaries...) still showed up under every
            // specialty's payroll, which is what was reported as the bug.
            ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId))
            ->orderBy('name')
            ->get()
            ->map(function (User $employee) use ($year, $month) {
                $payment = SalaryPayment::query()
                    ->where('user_id', $employee->id)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->first();

                // sum('amount') would over-report a partially-settled advance
                // by its full original amount instead of what's actually
                // still owed -- remainingAmount() is amount - settled_amount
                // (see SalaryPaymentController::allocateAdvances()).
                $unsettledAdvances = round(SalaryAdvance::query()
                    ->where('user_id', $employee->id)
                    ->unsettled()
                    ->get()
                    ->sum(fn (SalaryAdvance $advance) => $advance->remainingAmount()), 2);

                return [
                    'user_id' => $employee->id,
                    'name' => $employee->name,
                    'role_name' => $employee->roles->first()?->name,
                    'monthly_salary' => round((float) $employee->monthly_salary, 2),
                    'commission_percentage' => round((float) $employee->commission_percentage, 2),
                    'period_year' => $year,
                    'period_month' => $month,
                    'paid_this_period' => (bool) $payment,
                    'treatment_revenue' => round((float) ($payment->treatment_revenue ?? 0), 2),
                    'commission_amount' => round((float) ($payment->commission_amount ?? 0), 2),
                    'net_amount_this_period' => round((float) ($payment->net_amount ?? 0), 2),
                    'unsettled_advances' => $unsettledAdvances,
                ];
            })
            ->values();

        return $this->success([
            'period_year' => $year,
            'period_month' => $month,
            'employees' => $rows,
            'totals' => [
                'net_paid_this_period' => round($rows->sum('net_amount_this_period'), 2),
                'commission_this_period' => round($rows->sum('commission_amount'), 2),
                'unsettled_advances' => round($rows->sum('unsettled_advances'), 2),
            ],
        ]);
    }

    /**
     * Purchases (received purchase orders) vs. sales (InventorySaleItem,
     * the "Add From Inventory" patient-billing flow) vs. profit, per item.
     * Deliberately excludes plain manual in/out/adjustment transactions --
     * they carry no captured cost, so folding them in would fabricate
     * numbers. Both sides are scoped through the inventory item's own
     * specialty_id/branch_id, not a column on the purchase order or sale
     * itself (neither has a reliable one of its own).
     */
    public function inventoryReport(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
        ]);

        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;
        $branchId = $request->branch_id;
        $itemId = $request->inventory_item_id;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $itemScope = fn ($query, string $itemColumn) => $query
            ->when($specialtyId, fn ($q) => $q->whereHas($itemColumn, fn ($iq) => $iq->where('specialty_id', $specialtyId)))
            ->when($branchId, fn ($q) => $q->whereHas($itemColumn, fn ($iq) => $iq->where('branch_id', $branchId)))
            ->when($itemId, fn ($q) => $q->where('inventory_item_id', $itemId));

        $purchases = $itemScope(
            InventoryPurchaseOrder::query()
                ->where('status', InventoryPurchaseOrder::STATUS_RECEIVED)
                ->when($dateFrom, fn ($q) => $q->whereDate('received_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('received_at', '<=', $dateTo)),
            'item',
        )->get(['inventory_item_id', 'quantity', 'total_cost']);

        $sales = $itemScope(
            InventorySaleItem::query()
                ->whereHas('inventorySale', fn ($q) => $q
                    ->when($dateFrom, fn ($q2) => $q2->whereDate('created_at', '>=', $dateFrom))
                    ->when($dateTo, fn ($q2) => $q2->whereDate('created_at', '<=', $dateTo))),
            'inventoryItem',
        )->get(['inventory_item_id', 'quantity', 'unit_cost', 'line_total']);

        $itemIds = $purchases->pluck('inventory_item_id')->merge($sales->pluck('inventory_item_id'))->unique();
        $itemNames = InventoryItem::query()->whereIn('id', $itemIds)->pluck('name', 'id');

        $byItem = $itemIds->map(function ($id) use ($purchases, $sales, $itemNames) {
            $itemPurchases = $purchases->where('inventory_item_id', $id);
            $itemSales = $sales->where('inventory_item_id', $id);

            $purchaseCost = round((float) $itemPurchases->sum('total_cost'), 2);
            $revenue = round((float) $itemSales->sum('line_total'), 2);
            $costOfGoodsSold = round((float) $itemSales->sum(fn ($row) => (float) $row->quantity * (float) ($row->unit_cost ?? 0)), 2);

            return [
                'inventory_item_id' => $id,
                'item_name' => $itemNames->get($id),
                'quantity_purchased' => (float) $itemPurchases->sum('quantity'),
                'purchase_cost' => $purchaseCost,
                'quantity_sold' => (float) $itemSales->sum('quantity'),
                'sale_revenue' => $revenue,
                'profit' => round($revenue - $costOfGoodsSold, 2),
            ];
        })->sortByDesc('profit')->values();

        return $this->success([
            'items' => $byItem,
            'totals' => [
                'purchase_cost' => round((float) $byItem->sum('purchase_cost'), 2),
                'sale_revenue' => round((float) $byItem->sum('sale_revenue'), 2),
                'profit' => round((float) $byItem->sum('profit'), 2),
            ],
        ]);
    }
}
