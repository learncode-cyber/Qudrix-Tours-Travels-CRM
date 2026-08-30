<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

// Quotation requests from a tenant's own website.
//
// REWRITTEN: the previous version wrote to `package_id`, `travel_date`,
// `number_of_travelers`, `base_price`, `total_price` and
// `special_requirements` — none of which exist on `quotations` — and applied
// no tenant filter. It also hardcoded a 5/10% "group discount" in the
// controller, bypassing the auditable PricingEngine entirely.
//
// A website quote request is, in CRM terms, a LEAD. This version creates the
// lead, the customer, and a real draft quotation with proper line items.
class PublicQuotationController extends Controller
{
    private function tenantId(Request $request): ?int
    {
        return $request->apiKey->tenant_id ?? null;
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not bound to a tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'package_id' => 'required|integer',
            'travel_date' => 'required|date|after:today',
            'number_of_travelers' => 'required|integer|min:1|max:500',
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string|max:32',
            'special_requirements' => 'nullable|string|max:2000',
        ]);

        $package = Package::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($validated['package_id']);

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'Package not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $quotation = DB::transaction(function () use ($tenantId, $validated, $package) {
            $customer = Customer::where('tenant_id', $tenantId)
                ->where('email', $validated['customer']['email'])
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'name' => $validated['customer']['name'],
                    'email' => $validated['customer']['email'],
                    'phone' => $validated['customer']['phone'],
                    'customer_type' => 'individual',
                    'source' => 'website',
                    'is_active' => true,
                    'status' => 'active',
                ]);
            }

            // A website quote request IS a lead — creating one puts it into
            // the real sales pipeline instead of leaving an orphan quote.
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'name' => $validated['customer']['name'],
                'email' => $validated['customer']['email'],
                'phone' => $validated['customer']['phone'],
                'source' => 'website',
                'status' => 'new',
                'priority' => 'medium',
                'notes' => $validated['special_requirements'] ?? null,
            ]);

            $travellers = $validated['number_of_travelers'];
            $unitPrice = (float) $package->base_price;
            $subtotal = round($unitPrice * $travellers, 2);

            $quotation = Quotation::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'customer_id' => $customer->id,
                // No CRM user creates a website quote request.
                'created_by' => null,
                'source' => 'website',
                'quotation_number' => 'QT-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'share_token' => bin2hex(random_bytes(20)),
                'subject' => $package->name . ' — ' . $travellers . ' traveller(s)',
                'description' => $validated['special_requirements'] ?? null,
                // Deliberately DRAFT: a website request is not a priced
                // offer until staff review it. Discounts belong to the
                // auditable PricingEngine, not to a hardcoded rule here.
                'status' => 'draft',
                'currency' => 'USD',
                'valid_until' => now()->addDays(7),
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $subtotal,
            ]);

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'package_id' => $package->id,
                'description' => $package->name
                    . ($package->destination ? ' (' . $package->destination . ')' : '')
                    . ' — travel ' . $validated['travel_date'],
                'quantity' => $travellers,
                'unit_price' => $unitPrice,
                'cost_price' => $unitPrice,
                'tax_rate' => 0,
                'discount' => 0,
                'total' => $subtotal,
            ]);

            return $quotation;
        });

        return response()->json([
            'success' => true,
            'message' => 'Quotation request received. Our team will review and send you a final quote.',
            'data' => [
                'quotation_number' => $quotation->quotation_number,
                'status' => $quotation->status,
                'indicative_total' => (float) $quotation->total_amount,
                'currency' => $quotation->currency,
                'valid_until' => optional($quotation->valid_until)->toDateString(),
                'note' => 'This is an indicative total based on list price. Final pricing is confirmed by the agency.',
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, $number)
    {
        $tenantId = $this->tenantId($request);
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not bound to a tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $quotation = Quotation::where('tenant_id', $tenantId)
            ->where('quotation_number', $number)
            ->with('items')
            ->first();

        if (!$quotation) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'quotation_number' => $quotation->quotation_number,
                'subject' => $quotation->subject,
                'status' => $quotation->status,
                'subtotal' => (float) $quotation->subtotal,
                'discount_amount' => (float) $quotation->discount_amount,
                'tax_amount' => (float) $quotation->tax_amount,
                'total_amount' => (float) $quotation->total_amount,
                'currency' => $quotation->currency,
                'valid_until' => optional($quotation->valid_until)->toDateString(),
                'items' => $quotation->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'total' => (float) $i->total,
                ])->all(),
            ],
        ]);
    }
}
