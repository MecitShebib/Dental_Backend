<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function show(Request $request, Invoice $invoice)
    {
        // Company scoping alone left every invoice (client name + amount)
        // readable by id to any colleague; a doctor sees only their own
        // patients' invoices, same rule as the payments they come from.
        $this->assertActingDoctorOwnsClient($request, $invoice->client);

        return $this->success(InvoiceResource::make($invoice->load(['client', 'payment'])));
    }
}
