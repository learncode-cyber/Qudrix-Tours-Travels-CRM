<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Package;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public Quotation Controller
 * Used by website to request custom quotes
 *
 * PHASE 3 AUDIT FIXES applied to this file:
 * - Was writing to fields (base_price, total_price, travel_date,
 *   number_of_travelers, special_requirements, quoted_budget, package_id)
 *   that were not in Quotation::$fillable and had no backing columns —
 *   Eloquent silently drops non-fillable mass-assigned data, so none of
 *   this was ever actually saved. Both the model and the migration were
 *   extended (see 2024_01_01_000004b_extend_quotations_table.php) rather
 *   than continuing to lose it.
 * - Never set tenant_id, which is NOT NULL on `quotations` — every
 *   request would have thrown a database integrity error. Now reads it
 *   from $request->tenant_id, set by the (now actually applied, and now
 *   bug-fixed) ApiKeyMiddleware.
 * - Never set lead_id, which is NOT NULL on `quotations` (this is a
 *   B2B-style quotation-with-line-items schema, not a standalone
 *   customer-quote schema) — a website quote request is fundamentally a
 *   new Lead, so one is now created/found instead of silently violating
 *   the constraint.
 * - Read $quotation->package, ->travel_date, ->base_price, ->total_price
 *   in show() — none of which existed as relations/columns before this
 *   fix; ->travel_date->toIso8601String() would have fataled on null.
 */
class PublicQuotationController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|integer|exists:packages,id',
                'customer.name' => 'required|string|min:2|max:255',
                'customer.email' => 'required|email',
                'customer.phone' => 'required|string|min:7|max:20',
                'number_of_travelers' => 'required|integer|min:1|max:50',
                'travel_date' => 'required|date|after:today',
                'special_requirements' => 'nullable|string|max:1000',
                'budget' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tenantId = $request->tenant_id;
            if (!$tenantId) {
                // Should be unreachable once api.key.auth runs first, but
                // fail loudly rather than writing a tenant-less record.
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to resolve tenant from API key',
                    'code' => 'TENANT_NOT_RESOLVED',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $package = Package::where('id', $request->package_id)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package not found',
                    'code' => 'PACKAGE_NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            // A public quote request is a new Lead, not directly a
            // Customer — matches how the rest of the CRM models the
            // sales funnel (Lead -> Quotation -> Proposal -> won/lost).
            // PHASE 16: capture UTM parameters if the website passed
            // them through, for campaign attribution — real data if
            // present, left null (not invented) if not.
            $lead = Lead::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $request->customer['email']],
                [
                    'name' => $request->customer['name'],
                    'phone' => $request->customer['phone'],
                    'source' => 'website',
                    'status' => 'new',
                    'utm_source' => $request->input('utm_source'),
                    'utm_medium' => $request->input('utm_medium'),
                    'utm_campaign' => $request->input('utm_campaign'),
                    'utm_term' => $request->input('utm_term'),
                    'utm_content' => $request->input('utm_content'),
                ]
            );

            $basePrice = $package->base_price * $request->number_of_travelers;
            $discount = 0;

            if ($request->number_of_travelers >= 10) {
                $discount = $basePrice * 0.10;
            } elseif ($request->number_of_travelers >= 5) {
                $discount = $basePrice * 0.05;
            }

            $totalPrice = $basePrice - $discount;

            $quotation = Quotation::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'package_id' => $package->id,
                'quotation_number' => 'QT-' . Str::upper(Str::random(10)),
                'subject' => "Quote request: {$package->name}",
                'travel_date' => $request->travel_date,
                'number_of_travelers' => $request->number_of_travelers,
                'subtotal' => $basePrice,
                'discount_amount' => $discount,
                'total_amount' => $totalPrice,
                'currency' => $package->currency ?? 'USD',
                'special_requirements' => $request->special_requirements ?? null,
                'quoted_budget' => $request->budget ?? null,
                'status' => 'draft',
                'approval_status' => 'pending',
                'valid_until' => now()->addDays(7),
            ]);

            \Log::info('Quotation requested via public API', [
                'quotation_id' => $quotation->id,
                'tenant_id' => $tenantId,
                'package_id' => $package->id,
                'lead_email' => $lead->email,
                'travelers' => $request->number_of_travelers,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quotation request received successfully',
                'data' => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'status' => $quotation->status,
                    'subtotal' => $quotation->subtotal,
                    'discount_amount' => $quotation->discount_amount,
                    'total_amount' => $quotation->total_amount,
                    'currency' => $quotation->currency,
                    'number_of_travelers' => $quotation->number_of_travelers,
                    'valid_until' => $quotation->valid_until->toIso8601String(),
                    'created_at' => $quotation->created_at->toIso8601String(),
                ],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'api_version' => 'v1',
                    'next_step' => 'Our team will review and send a detailed quotation within 24 hours',
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Public quotation creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create quotation',
                'code' => 'CREATION_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request, $number)
    {
        try {
            $quotation = Quotation::where('quotation_number', $number)
                ->where('tenant_id', $request->tenant_id)
                ->with('package', 'lead')
                ->first();

            if (!$quotation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found',
                    'code' => 'NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'package_name' => $quotation->package->name ?? null,
                    'status' => $quotation->status,
                    'travel_date' => optional($quotation->travel_date)->toIso8601String(),
                    'number_of_travelers' => $quotation->number_of_travelers,
                    'subtotal' => $quotation->subtotal,
                    'discount_amount' => $quotation->discount_amount,
                    'discount_percentage' => $quotation->subtotal > 0
                        ? round(($quotation->discount_amount / $quotation->subtotal) * 100, 2)
                        : 0,
                    'total_amount' => $quotation->total_amount,
                    'currency' => $quotation->currency,
                    'price_per_person' => $quotation->number_of_travelers
                        ? round($quotation->total_amount / $quotation->number_of_travelers, 2)
                        : null,
                    'valid_until' => optional($quotation->valid_until)->toIso8601String(),
                    'special_requirements' => $quotation->special_requirements,
                    'created_at' => $quotation->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch quotation',
                'code' => 'FETCH_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PHASE 3: customer acceptance — the customer-facing accept/reject
     * action on a quotation they received, reached via the public API
     * (e.g. a link in the emailed quotation) rather than requiring staff
     * login.
     */
    public function respond(Request $request, $number)
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
            'reason' => 'nullable|string|max:1000',
        ]);

        $quotation = Quotation::where('quotation_number', $number)
            ->where('tenant_id', $request->tenant_id)
            ->first();

        if (!$quotation) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found',
                'code' => 'NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($quotation->status !== 'sent') {
            return response()->json([
                'success' => false,
                'message' => 'Only a sent quotation can be accepted or rejected',
                'code' => 'INVALID_STATE',
            ], Response::HTTP_CONFLICT);
        }

        if ($quotation->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This quotation has expired',
                'code' => 'EXPIRED',
            ], Response::HTTP_GONE);
        }

        if ($validated['action'] === 'accept') {
            $quotation->update(['status' => 'accepted']);
            $quotation->lead?->update(['status' => 'negotiation']);
        } else {
            $quotation->update([
                'status' => 'rejected',
                'notes' => trim(($quotation->notes ?? '') . "\n[Customer rejected] " . ($validated['reason'] ?? '')),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Quotation {$validated['action']}ed successfully",
            'data' => ['status' => $quotation->status],
        ]);
    }
}
