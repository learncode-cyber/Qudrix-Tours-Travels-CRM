<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerFamily;
use App\Models\Note;
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

    // Customer 360: a single aggregated view of everything attached to a
    // customer, as required by the CRM spec. Reuses the real, already
    // built sub-endpoints (timeline) rather than duplicating their logic.
    public function profile360(Request $request, $id)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)
            ->with(['family', 'bookings', 'leads', 'quotations', 'deals', 'communications', 'tags'])
            ->findOrFail($id);

        $notes = Note::where('tenant_id', $customer->tenant_id)
            ->where('notable_type', Customer::class)
            ->where('notable_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $timeline = app(CustomerTimelineController::class)
            ->show($request, $id)
            ->getData(true)['data'];

        return response()->json([
            'data' => [
                'customer' => $customer,
                'leads' => $customer->leads,
                'deals' => $customer->deals,
                'bookings' => $customer->bookings,
                'quotations' => $customer->quotations,
                'communications' => $customer->communications,
                'notes' => $notes,
                'tags' => $customer->tags,
                'timeline' => $timeline,
            ],
        ]);
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
}
