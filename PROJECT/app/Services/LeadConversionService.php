<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quotation;

// Directive requirement: a won lead must become a customer without ever
// creating a duplicate record. Shared by every code path that can move a
// lead to 'won' (LeadController::updateStatus, PipelineController::
// updateLeadStage) so the same reuse-before-create rule always applies.
class LeadConversionService
{
    public function convertIfWon(Lead $lead, string $newStatus): void
    {
        if ($newStatus !== 'won' || $lead->customer_id) {
            return;
        }

        $customer = $this->findOrCreateCustomer($lead);
        $lead->update(['customer_id' => $customer->id]);

        // Quotations are commonly created against just a lead_id
        // (customer_id is optional at creation time, since the lead may
        // not have converted yet). Backfill it now so
        // Customer::quotations() — the customer quotation history the
        // directive asks for — actually finds them, instead of every
        // quotation silently vanishing from that history the moment its
        // lead converts.
        Quotation::where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id]);
    }

    private function findOrCreateCustomer(Lead $lead): Customer
    {
        $query = Customer::where('tenant_id', $lead->tenant_id);

        if ($lead->email) {
            $existing = (clone $query)->where('email', $lead->email)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($lead->phone) {
            $existing = (clone $query)->where('phone', $lead->phone)->first();
            if ($existing) {
                return $existing;
            }
        }

        return Customer::create([
            'tenant_id' => $lead->tenant_id,
            'branch_id' => $lead->branch_id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'customer_type' => 'individual',
            'source' => $lead->source,
            'is_active' => true,
            'status' => 'active',
        ]);
    }
}
