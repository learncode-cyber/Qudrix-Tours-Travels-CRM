<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Lead;

class QuotationService
{
    public function createQuotation(int $tenantId, array $data)
    {
        $quotation = Quotation::create([
            'tenant_id' => $tenantId,
            'quotation_number' => 'QT-' . time(),
            'status' => 'draft',
            'tax_amount' => 0,
            'discount_amount' => 0,
            ...$data
        ]);

        return $quotation;
    }

    public function addItemToQuotation(Quotation $quotation, array $itemData)
    {
        $itemTotal = ($itemData['quantity'] * $itemData['unit_price']) - ($itemData['discount'] ?? 0);
        $tax = $itemTotal * (($itemData['tax_rate'] ?? 0) / 100);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'total' => $itemTotal + $tax,
            ...$itemData
        ]);

        // Recalculate quotation totals
        $quotation->calculateTotals();

        return $item;
    }

    public function calculateQuotationValue(Quotation $quotation)
    {
        $subtotal = $quotation->items()->sum('total');
        return [
            'subtotal' => $subtotal,
            'tax' => $quotation->tax_amount,
            'discount' => $quotation->discount_amount,
            'total' => $subtotal + $quotation->tax_amount - $quotation->discount_amount,
        ];
    }

    public function getQuotationConversionRate(int $tenantId)
    {
        $total = Quotation::where('tenant_id', $tenantId)->count();
        if ($total === 0) return 0;

        $accepted = Quotation::where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->count();

        return round(($accepted / $total) * 100, 2);
    }
}
