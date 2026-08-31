<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $revenueThisMonth = Invoice::where('tenant_id', $tenantId)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->sum('paid_amount');

        $totalQuotations = Quotation::where('tenant_id', $tenantId)->count();
        $acceptedQuotations = Quotation::where('tenant_id', $tenantId)->where('status', 'accepted')->count();
        $quotationConversionRate = $totalQuotations > 0
            ? round(($acceptedQuotations / $totalQuotations) * 100, 2)
            : 0;

        $totalInvoiced = (float) Invoice::where('tenant_id', $tenantId)->sum('total_amount');
        $totalCollected = (float) Invoice::where('tenant_id', $tenantId)->sum('paid_amount');
        $invoiceCollectionRate = $totalInvoiced > 0
            ? round(($totalCollected / $totalInvoiced) * 100, 2)
            : 0;

        $outstandingAmount = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->balanceDue());

        $topPackages = Booking::where('bookings.tenant_id', $tenantId)
            ->join('packages', 'packages.id', '=', 'bookings.package_id')
            ->selectRaw('packages.id as package_id, packages.name, COUNT(*) as count, SUM(bookings.total_amount) as revenue')
            ->groupBy('packages.id', 'packages.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'revenue_this_month' => $revenueThisMonth,
                'quotation_conversion_rate' => $quotationConversionRate,
                'invoice_collection_rate' => $invoiceCollectionRate,
                'outstanding_amount' => round($outstandingAmount, 2),
                'top_packages' => $topPackages,
            ],
        ]);
    }
}
