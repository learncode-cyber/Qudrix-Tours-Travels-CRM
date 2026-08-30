<?php
namespace App\Http\Controllers;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuotationPdfController extends Controller
{
    public function download(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)
            ->with('items', 'customer')
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $quotation]);

        return $pdf->download($quotation->quotation_number . '.pdf');
    }
}
