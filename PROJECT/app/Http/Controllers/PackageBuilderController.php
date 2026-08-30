<?php
namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\InventoryResolver;
use App\Services\PricingEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Deterministic Custom Package Builder (Directive S6, non-AI foundation —
// the AI-assisted natural-language version is Phase 10 and calls into
// this same builder rather than replacing it). Every component is looked
// up against real inventory; nothing is ever invented. Cost is computed
// from real unit prices, then run through PricingEngine for a fully
// auditable markup.
class PackageBuilderController extends Controller
{
    public function __construct(private InventoryResolver $inventory)
    {
    }

    public function build(Request $request, PricingEngine $pricingEngine)
    {
        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'customer_id' => 'nullable|exists:customers,id',
            'destination' => 'required|string',
            'travel_date' => 'required|date',
            'group_size' => 'required|integer|min:1',
            'components' => 'required|array|min:1',
            'components.*.type' => 'required|in:hotel,flight,transport',
            'components.*.reference_id' => 'required|integer',
            'components.*.quantity' => 'required|integer|min:1',
            'save_as_package' => 'boolean',
            'create_quotation' => 'boolean',
        ]);

        $tenantId = $request->user->tenant_id;

        $resolved = $this->inventory->resolveAll($tenantId, $validated['components']);
        $lines = $resolved['lines'];
        $baseCost = $resolved['base_cost'];

        $daysBeforeTravel = now()->diffInDays(\Illuminate\Support\Carbon::parse($validated['travel_date']), false);

        $pricing = $pricingEngine->calculate($tenantId, $baseCost, [
            'travel_date' => $validated['travel_date'],
            'group_size' => $validated['group_size'],
            'booking_days_before_travel' => max(0, (int) $daysBeforeTravel),
        ], $request->user->id);

        $response = [
            'destination' => $validated['destination'],
            'travel_date' => $validated['travel_date'],
            'group_size' => $validated['group_size'],
            'components' => $lines,
            'pricing' => $pricing,
        ];

        if ($validated['save_as_package'] ?? false) {
            $package = Package::create([
                'tenant_id' => $tenantId,
                'name' => 'Custom: ' . $validated['destination'] . ' (' . now()->format('Y-m-d H:i') . ')',
                'code' => 'CUSTOM-' . strtoupper(bin2hex(random_bytes(3))),
                'type' => 'custom',
                'destination' => $validated['destination'],
                'base_price' => $pricing['final_price'],
                'inclusions' => array_column($lines, 'description'),
                'is_active' => true,
                'status' => 'active',
                'is_custom_built' => true,
                'components' => $validated['components'],
                'built_by' => $request->user->id,
                'built_for_customer_id' => $validated['customer_id'] ?? null,
            ]);
            $response['package'] = $package;
        }

        if ($validated['create_quotation'] ?? false) {
            if (empty($validated['lead_id'])) {
                throw ValidationException::withMessages(['lead_id' => 'lead_id is required to create a quotation']);
            }
            $quotation = Quotation::create([
                'tenant_id' => $tenantId,
                'lead_id' => $validated['lead_id'],
                'customer_id' => $validated['customer_id'] ?? null,
                'created_by' => $request->user->id,
                'quotation_number' => 'QT-' . time(),
                'share_token' => bin2hex(random_bytes(20)),
                'subject' => 'Custom Package: ' . $validated['destination'],
                'status' => 'draft',
                'currency' => 'USD',
                'valid_until' => now()->addDays(7),
                'tax_amount' => 0,
                'discount_amount' => 0,
                'subtotal' => 0,
                'total_amount' => 0,
            ]);

            foreach ($lines as $line) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['unit_price'],
                    'total' => $line['line_total'],
                ]);
            }

            $markupAmount = round($pricing['final_price'] - $baseCost, 2);
            if ($markupAmount != 0) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'description' => 'Pricing adjustment (rule-based, see pricing_calculation_logs #' . $pricing['calculation_log_id'] . ')',
                    'quantity' => 1,
                    'unit_price' => $markupAmount,
                    'total' => $markupAmount,
                ]);
            }

            $quotation->update(['subtotal' => $pricing['final_price'], 'total_amount' => $pricing['final_price']]);
            $response['quotation'] = $quotation->load('items');
        }

        return response()->json(['data' => $response], 201);
    }
}
