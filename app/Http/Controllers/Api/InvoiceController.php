<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        return $this->success(InvoiceResource::make($invoice->load(['client', 'payment'])));
    }
}
