<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Lead;
use App\Models\Customer;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $query = Quotation::where('tenant_id', $request->user->tenant_id)
            ->with('lead', 'customer', 'items');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->lead_id) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('quotation_number', 'like', "%{$request->search}%")
                  ->orWhere('subject', 'like', "%{$request->search}%");
            });
        }

        $quotations = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $quotations->items(),
            'pagination' => [
                'total' => $quotations->total(),
                'per_page' => $quotations->perPage(),
                'current_page' => $quotations->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'customer_id' => 'nullable|exists:customers,id',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'valid_until' => 'required|date',
            'currency' => 'required|string|size:3',
            'items' => 'required|array|min:1',
            'items.*.package_id' => 'nullable|exists:packages,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.markup_percentage' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'items.*.discount' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|array',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'quotation_number' => 'QT-' . time(),
            'share_token' => bin2hex(random_bytes(20)),
            'status' => 'draft',
            'tax_amount' => 0,
            'discount_amount' => 0,
            'subtotal' => 0,
            'total_amount' => 0,
            ...$validated
        ]);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $unitPrice = $item['unit_price'];
            if (isset($item['cost_price'], $item['markup_percentage'])) {
                $unitPrice = QuotationItem::priceFromMarkup($item['cost_price'], $item['markup_percentage']);
            }
            $itemTotal = ($item['quantity'] * $unitPrice) - ($item['discount'] ?? 0);
            $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'package_id' => $item['package_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'cost_price' => $item['cost_price'] ?? null,
                'markup_percentage' => $item['markup_percentage'] ?? null,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'discount' => $item['discount'] ?? 0,
                'total' => $itemTotal + $tax,
            ]);

            $subtotal += $itemTotal + $tax;
        }

        $quotation->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);

        return response()->json([
            'message' => 'Quotation created successfully',
            'data' => $quotation->load('items')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)
            ->with('lead', 'customer', 'items', 'proposals')
            ->findOrFail($id);

        return response()->json(['data' => $quotation]);
    }

    public function update(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($quotation->status !== 'draft') {
            return response()->json(['error' => 'Can only edit draft quotations'], 400);
        }

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'valid_until' => 'sometimes|date',
            'payment_terms' => 'nullable|string',
        ]);

        $quotation->update($validated);

        return response()->json([
            'message' => 'Quotation updated',
            'data' => $quotation
        ]);
    }

    public function sendQuotation(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($quotation->requires_approval && !$quotation->approved_at) {
            return response()->json(['error' => 'Quotation requires approval before it can be sent'], 400);
        }

        if (!in_array($quotation->status, ['draft', 'pending_approval', 'approved'])) {
            return response()->json(['error' => 'Quotation already sent'], 400);
        }

        $quotation->update(['status' => 'sent']);

        return response()->json([
            'message' => 'Quotation sent successfully',
            'data' => $quotation
        ]);
    }

    public function submitForApproval(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        if ($quotation->status !== 'draft') {
            return response()->json(['error' => 'Only draft quotations can be submitted for approval'], 400);
        }
        $quotation->update(['status' => 'pending_approval', 'requires_approval' => true]);
        return response()->json(['data' => $quotation]);
    }

    public function approve(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        if ($quotation->status !== 'pending_approval') {
            return response()->json(['error' => 'Quotation is not pending approval'], 400);
        }
        $quotation->update([
            'status' => 'approved',
            'approved_by' => $request->user->id,
            'approved_at' => now(),
        ]);
        return response()->json(['data' => $quotation]);
    }

    // Creates a new draft version linked back to the original, for
    // revising a quotation that has already been sent (preserves the sent
    // version's history instead of mutating it in place).
    public function newVersion(Request $request, $id)
    {
        $original = Quotation::where('tenant_id', $request->user->tenant_id)->with('items')->findOrFail($id);

        $copy = Quotation::create([
            'tenant_id' => $original->tenant_id,
            'lead_id' => $original->lead_id,
            'customer_id' => $original->customer_id,
            'created_by' => $request->user->id,
            'quotation_number' => 'QT-' . time(),
            'share_token' => bin2hex(random_bytes(20)),
            'subject' => $original->subject,
            'description' => $original->description,
            'status' => 'draft',
            'currency' => $original->currency,
            'valid_until' => $original->valid_until,
            'notes' => $original->notes,
            'payment_terms' => $original->payment_terms,
            'version' => $original->version + 1,
            'supersedes_quotation_id' => $original->id,
            'tax_amount' => $original->tax_amount,
            'discount_amount' => $original->discount_amount,
            'subtotal' => 0,
            'total_amount' => 0,
        ]);

        $subtotal = 0;
        foreach ($original->items as $item) {
            QuotationItem::create([
                'quotation_id' => $copy->id,
                'package_id' => $item->package_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'cost_price' => $item->cost_price,
                'markup_percentage' => $item->markup_percentage,
                'tax_rate' => $item->tax_rate,
                'discount' => $item->discount,
                'total' => $item->total,
            ]);
            $subtotal += $item->total;
        }
        $copy->update(['subtotal' => $subtotal, 'total_amount' => $subtotal + $copy->tax_amount - $copy->discount_amount]);

        return response()->json(['data' => $copy->load('items')], 201);
    }

    public function getQuotationStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stats = [
            'total' => Quotation::where('tenant_id', $tenantId)->count(),
            'draft' => Quotation::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'sent' => Quotation::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
            'accepted' => Quotation::where('tenant_id', $tenantId)->where('status', 'accepted')->count(),
            'rejected' => Quotation::where('tenant_id', $tenantId)->where('status', 'rejected')->count(),
            'total_value' => Quotation::where('tenant_id', $tenantId)->sum('total_amount'),
            'average_value' => Quotation::where('tenant_id', $tenantId)->average('total_amount'),
        ];

        return response()->json(['data' => $stats]);
    }
}
