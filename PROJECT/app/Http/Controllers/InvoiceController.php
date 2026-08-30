<?php
namespace App\Http\Controllers;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::where('tenant_id', $request->user->tenant_id)
            ->with('customer')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest()
            ->paginate($request->per_page ?? 20);
        return response()->json(['data' => $invoices->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'currency' => 'required|string|size:3',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad((string) (Invoice::where('tenant_id', $request->user->tenant_id)->count() + 1), 5, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            ...$validated,
        ]);

        $subtotal = 0;
        $taxTotal = 0;
        $discountTotal = 0;
        foreach ($validated['items'] as $item) {
            $lineBase = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
            $lineTax = $lineBase * (($item['tax_rate'] ?? 0) / 100);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'discount' => $item['discount'] ?? 0,
                'total' => $lineBase + $lineTax,
            ]);
            $subtotal += $lineBase;
            $taxTotal += $lineTax;
            $discountTotal += $item['discount'] ?? 0;
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxTotal,
            'discount_amount' => $discountTotal,
            'total_amount' => $subtotal + $taxTotal,
        ]);

        return response()->json(['data' => $invoice->load('items')], 201);
    }

    public function show(Request $request, $id)
    {
        $invoice = Invoice::where('tenant_id', $request->user->tenant_id)->with('items', 'customer')->findOrFail($id);
        return response()->json(['data' => $invoice]);
    }

    public function recordPayment(Request $request, $id)
    {
        $invoice = Invoice::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max($invoice->balanceDue(), 0.01),
        ]);
        $invoice->increment('paid_amount', $validated['amount']);
        $invoice->refresh()->recalculateStatus();
        return response()->json(['data' => $invoice]);
    }

    public function send(Request $request, $id)
    {
        $invoice = Invoice::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        if ($invoice->status !== 'draft') {
            return response()->json(['error' => 'Invoice already sent'], 400);
        }
        $invoice->update(['status' => 'sent']);
        return response()->json(['data' => $invoice]);
    }

    public function stats(Request $request)
    {
        $tenantId = $request->user->tenant_id;
        return response()->json(['data' => [
            'total' => Invoice::where('tenant_id', $tenantId)->count(),
            'outstanding' => Invoice::where('tenant_id', $tenantId)->whereIn('status', ['sent', 'partially_paid', 'overdue'])->sum('total_amount'),
            'paid' => Invoice::where('tenant_id', $tenantId)->where('status', 'paid')->sum('total_amount'),
            'overdue' => Invoice::where('tenant_id', $tenantId)->where('status', 'overdue')->count(),
        ]]);
    }
}
