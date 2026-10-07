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

        // Approval workflow: if this tenant has configured an approval
        // threshold (Settings key 'quotation_approval_threshold') and this
        // quotation's total exceeds it, block sending until approved.
        $threshold = \App\Models\Setting::where('tenant_id', $request->user->tenant_id)
            ->where('key', 'quotation_approval_threshold')
            ->value('value');

        if ($threshold !== null && $quotation->total_amount > (float) $threshold) {
            if ($quotation->approval_status === 'not_required') {
                $quotation->update(['approval_status' => 'pending']);
            }

            if ($quotation->approval_status !== 'approved') {
                return response()->json([
                    'error' => 'This quotation exceeds the approval threshold and must be approved before sending',
                    'approval_status' => $quotation->approval_status,
                ], 403);
            }
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
            'average_value' => round(Quotation::where('tenant_id', $tenantId)->avg('total_amount') ?? 0, 2),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * PHASE 3: quote versioning — clones a quotation (and its items) as a
     * new version linked back to the original via parent_quotation_id,
     * rather than mutating a quotation a customer may already be
     * reviewing.
     */
    public function createVersion(Request $request, $id)
    {
        $original = Quotation::where('tenant_id', $request->user->tenant_id)
            ->with('items')
            ->findOrFail($id);

        $latestVersion = Quotation::where('tenant_id', $request->user->tenant_id)
            ->where(function ($q) use ($original) {
                $rootId = $original->parent_quotation_id ?? $original->id;
                $q->where('id', $rootId)->orWhere('parent_quotation_id', $rootId);
            })
            ->max('version') ?? $original->version;

        $newQuotation = $original->replicate(['quotation_number', 'status']);
        $newQuotation->quotation_number = 'QT-' . time();
        $newQuotation->status = 'draft';
        $newQuotation->approval_status = 'not_required';
        $newQuotation->version = $latestVersion + 1;
        $newQuotation->parent_quotation_id = $original->parent_quotation_id ?? $original->id;
        $newQuotation->save();

        foreach ($original->items as $item) {
            $newItem = $item->replicate();
            $newItem->quotation_id = $newQuotation->id;
            $newItem->save();
        }

        return response()->json([
            'message' => 'New quotation version created',
            'data' => $newQuotation->load('items'),
        ], 201);
    }

    /**
     * PHASE 3: approval workflow — a quotation above a tenant-configured
     * threshold requires manager sign-off before it can be sent. The
     * threshold itself is read from Settings (per-tenant, admin-configured)
     * rather than a hardcoded number, since "approval required above $X"
     * legitimately varies per business.
     */
    public function approve(Request $request, $id)
    {
        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($quotation->approval_status !== 'pending') {
            return response()->json(['error' => 'Quotation is not pending approval'], 400);
        }

        $quotation->update(['approval_status' => 'approved']);

        return response()->json(['message' => 'Quotation approved', 'data' => $quotation]);
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'nullable|string']);

        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($quotation->approval_status !== 'pending') {
            return response()->json(['error' => 'Quotation is not pending approval'], 400);
        }

        $quotation->update([
            'approval_status' => 'rejected',
            'notes' => trim(($quotation->notes ?? '') . "\n[Approval rejected] " . ($validated['reason'] ?? '')),
        ]);

        return response()->json(['message' => 'Quotation rejected', 'data' => $quotation]);
    }
}
