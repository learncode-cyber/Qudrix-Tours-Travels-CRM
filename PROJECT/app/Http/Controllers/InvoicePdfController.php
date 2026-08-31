<?php
namespace App\Http\Controllers;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoicePdfController extends Controller
{
    public function download(Request $request, $id)
    {
        $invoice = Invoice::where('tenant_id', $request->user->tenant_id)
            ->with('items', 'customer', 'quotation')
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        return $pdf->download($invoice->invoice_number . '.pdf');
    }
}
