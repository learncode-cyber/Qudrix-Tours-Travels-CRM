<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::where('tenant_id', $request->user->tenant_id);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->country) {
            $query->where('country', $request->country);
        }

        $customers = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $customers->items(),
            'pagination' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers',
            'phone' => 'nullable|string|max:20',
            'customer_type' => 'required|in:individual,corporate,group',
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
        ]);

        $customer = Customer::create([
            'tenant_id' => $request->user->tenant_id,
            'branch_id' => $request->branch_id,
            ...$validated,
            'is_active' => true,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)
            ->with('family', 'bookings', 'leads')
            ->findOrFail($id);

        return response()->json([
            'data' => $customer,
            'family_count' => $customer->family->count(),
            'booking_count' => $customer->bookings->count(),
            'lead_count' => $customer->leads->count(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:customers,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'status' => 'sometimes|in:active,inactive',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    public function delete(Request $request, $id)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }

    public function addFamily(Request $request, $customerId)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|in:spouse,child,parent,sibling,relative,friend',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
        ]);

        $family = CustomerFamily::create([
            'tenant_id' => $request->user->tenant_id,
            'customer_id' => $customerId,
            ...$validated
        ]);

        return response()->json([
            'message' => 'Family member added',
            'data' => $family
        ], 201);
    }

    public function getFamily(Request $request, $customerId)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);
        $family = $customer->family;

        return response()->json([
            'data' => $family,
            'count' => $family->count()
        ]);
    }

    /**
     * PHASE 2: Customer Timeline — a single chronological feed of every
     * customer interaction, pulled from the tables that already existed
     * (communications, tasks, bookings, payments, quotations) rather than
     * introducing a separate duplicated "activity log" table.
     */
    public function timeline(Request $request, $customerId)
    {
        $tenantId = $request->user->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $events = collect();

        $customer->communications()
            ->get()
            ->each(function ($c) use ($events) {
                $events->push([
                    'type' => 'communication',
                    'subtype' => $c->type ?? null,
                    'summary' => $c->subject ?? $c->message ?? null,
                    'occurred_at' => $c->created_at,
                    'ref_id' => $c->id,
                ]);
            });

        \App\Models\Task::where('tenant_id', $tenantId)
            ->where('related_entity_type', 'customer')
            ->where('related_entity_id', $customerId)
            ->get()
            ->each(function ($t) use ($events) {
                $events->push([
                    'type' => 'task',
                    'subtype' => $t->type,
                    'summary' => $t->title,
                    'occurred_at' => $t->created_at,
                    'ref_id' => $t->id,
                ]);
            });

        $customer->bookings()
            ->get()
            ->each(function ($b) use ($events) {
                $events->push([
                    'type' => 'booking',
                    'subtype' => $b->status,
                    'summary' => $b->booking_number ?? "Booking #{$b->id}",
                    'occurred_at' => $b->created_at,
                    'ref_id' => $b->id,
                ]);
            });

        \App\Models\Payment::where('tenant_id', $tenantId)
            ->whereIn('booking_id', $customer->bookings()->pluck('id'))
            ->get()
            ->each(function ($p) use ($events) {
                $events->push([
                    'type' => 'payment',
                    'subtype' => $p->status,
                    'summary' => "Payment of {$p->amount}",
                    'occurred_at' => $p->created_at,
                    'ref_id' => $p->id,
                ]);
            });

        // PHASE 8 AUDIT FINDING: quotations, proposals, and invoices were
        // never included here, even though the phase spec explicitly
        // requires "Quotes ... Everything must appear in the customer
        // timeline." A customer's full sales-to-fulfillment history was
        // silently incomplete.
        \App\Models\Quotation::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->get()
            ->each(function ($q) use ($events) {
                $events->push([
                    'type' => 'quotation',
                    'subtype' => $q->status,
                    'summary' => $q->quotation_number . ' - ' . ($q->subject ?? ''),
                    'occurred_at' => $q->created_at,
                    'ref_id' => $q->id,
                ]);
            });

        \App\Models\Proposal::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->get()
            ->each(function ($p) use ($events) {
                $events->push([
                    'type' => 'proposal',
                    'subtype' => $p->status,
                    'summary' => $p->proposal_number . ' - ' . ($p->title ?? ''),
                    'occurred_at' => $p->created_at,
                    'ref_id' => $p->id,
                ]);
            });

        \App\Models\Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->get()
            ->each(function ($i) use ($events) {
                $events->push([
                    'type' => 'invoice',
                    'subtype' => $i->status,
                    'summary' => "{$i->invoice_number} - {$i->total_amount} {$i->currency}",
                    'occurred_at' => $i->created_at,
                    'ref_id' => $i->id,
                ]);
            });

        $sorted = $events->sortByDesc('occurred_at')->values();

        return response()->json([
            'data' => $sorted,
            'count' => $sorted->count(),
        ]);
    }
}
