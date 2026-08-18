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
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|between:0,100',
            'items.*.discount' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'quotation_number' => 'QT-' . time(),
            'status' => 'draft',
            'tax_amount' => 0,
            'discount_amount' => 0,
            'subtotal' => 0,
            'total_amount' => 0,
            ...$validated
        ]);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $itemTotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
            $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
            
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
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

        if ($quotation->status !== 'draft') {
            return response()->json(['error' => 'Quotation already sent'], 400);
        }

        $quotation->update(['status' => 'sent']);

        return response()->json([
            'message' => 'Quotation sent successfully',
            'data' => $quotation
        ]);
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
