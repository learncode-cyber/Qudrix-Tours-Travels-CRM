<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::where('tenant_id', $request->user->tenant_id)
            ->with('customer', 'proposal');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->overdue) {
            $query->where('due_date', '<', now())
                ->whereRaw('amount_paid < total_amount');
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $invoices->items(),
            'pagination' => [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
            ],
        ]);
    }

    /**
     * Generate an invoice from a signed Proposal (via its Quotation for
     * line-item totals). This is the normal path — invoices in a B2B
     * travel CRM follow from an accepted proposal, not created from thin
     * air.
     */
    public function createFromProposal(Request $request)
    {
        $validated = $request->validate([
            'proposal_id' => 'required|exists:proposals,id',
            'due_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string',
        ]);

        $proposal = Proposal::where('tenant_id', $request->user->tenant_id)
            ->with('quotation')
            ->findOrFail($validated['proposal_id']);

        if ($proposal->status !== 'signed') {
            return response()->json([
                'error' => 'Invoices can only be generated from signed proposals',
            ], 400);
        }

        if (!$proposal->customer_id) {
            return response()->json([
                'error' => 'Proposal has no linked customer — convert the lead to a customer first',
            ], 400);
        }

        $quotation = $proposal->quotation;

        $invoice = Invoice::create([
            'tenant_id' => $request->user->tenant_id,
            'quotation_id' => $quotation?->id,
            'proposal_id' => $proposal->id,
            'customer_id' => $proposal->customer_id,
            'created_by' => $request->user->id,
            'invoice_number' => 'INV-' . now()->format('Ym') . '-' . Str::upper(Str::random(6)),
            'status' => 'sent',
            'subtotal' => $quotation->subtotal ?? 0,
            'tax_amount' => $quotation->tax_amount ?? 0,
            'discount_amount' => $quotation->discount_amount ?? 0,
            'total_amount' => $quotation->total_amount ?? 0,
            'currency' => $quotation->currency ?? 'USD',
            'issue_date' => now(),
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Invoice generated successfully',
            'data' => $invoice,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $invoice = Invoice::where('tenant_id', $request->user->tenant_id)
            ->with('customer', 'proposal', 'quotation', 'payments')
            ->findOrFail($id);

        return response()->json([
            'data' => $invoice,
            'balance_due' => $invoice->balance_due,
            'is_overdue' => $invoice->isOverdue(),
        ]);
    }

    public function void(Request $request, $id)
    {
        $invoice = Invoice::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($invoice->amount_paid > 0) {
            return response()->json([
                'error' => 'Cannot void an invoice that has payments recorded against it',
            ], 400);
        }

        $invoice->update(['status' => 'void']);

        return response()->json(['message' => 'Invoice voided', 'data' => $invoice]);
    }

    public function getInvoiceStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stats = [
            'total' => Invoice::where('tenant_id', $tenantId)->count(),
            'paid' => Invoice::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
            'partially_paid' => Invoice::where('tenant_id', $tenantId)->where('status', 'partially_paid')->count(),
            'overdue' => Invoice::where('tenant_id', $tenantId)
                ->where('due_date', '<', now())
                ->whereRaw('amount_paid < total_amount')
                ->count(),
            'total_invoiced' => (float) Invoice::where('tenant_id', $tenantId)->sum('total_amount'),
            'total_collected' => (float) Invoice::where('tenant_id', $tenantId)->sum('amount_paid'),
        ];
        $stats['total_outstanding'] = round($stats['total_invoiced'] - $stats['total_collected'], 2);

        return response()->json(['data' => $stats]);
    }
}
