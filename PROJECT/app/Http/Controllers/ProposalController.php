<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\Customer;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $query = Proposal::where('tenant_id', $request->user->tenant_id)
            ->with('quotation', 'lead', 'customer');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->lead_id) {
            $query->where('lead_id', $request->lead_id);
        }

        $proposals = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $proposals->items(),
            'pagination' => [
                'total' => $proposals->total(),
                'per_page' => $proposals->perPage(),
            ]
        ]);
    }

    public function createFromQuotation(Request $request)
    {
        $validated = $request->validate([
            'quotation_id' => 'required|exists:quotations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'expiry_date' => 'required|date|after:today',
            'notes' => 'nullable|string',
        ]);

        $quotation = Quotation::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($validated['quotation_id']);

        $proposal = Proposal::create([
            'tenant_id' => $request->user->tenant_id,
            'quotation_id' => $quotation->id,
            'lead_id' => $quotation->lead_id,
            'customer_id' => $quotation->customer_id,
            'proposal_number' => 'PROP-' . time(),
            'status' => 'draft',
            'proposal_date' => now(),
            'created_by' => $request->user->id,
            ...$validated
        ]);

        return response()->json([
            'message' => 'Proposal created from quotation',
            'data' => $proposal
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $proposal = Proposal::where('tenant_id', $request->user->tenant_id)
            ->with('quotation', 'lead', 'customer')
            ->findOrFail($id);

        return response()->json(['data' => $proposal]);
    }

    public function sendProposal(Request $request, $id)
    {
        $proposal = Proposal::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($proposal->status !== 'draft') {
            return response()->json(['error' => 'Proposal already sent'], 400);
        }

        $proposal->markAsSent();

        return response()->json([
            'message' => 'Proposal sent successfully',
            'data' => $proposal
        ]);
    }

    public function signProposal(Request $request, $id)
    {
        $validated = $request->validate([
            'commission_rate' => 'nullable|numeric|between:0,100',
        ]);

        $proposal = Proposal::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($proposal->status !== 'sent') {
            return response()->json(['error' => 'Proposal must be sent first'], 400);
        }

        $proposal->markAsSigned();

        if (isset($validated['commission_rate'])) {
            $proposal->update(['commission_rate' => $validated['commission_rate']]);
        }

        // PHASE 8: a signed proposal is a won deal — the CRM chain's
        // Lead -> Customer step must actually happen here, not stay
        // manual, or every downstream step (Invoice requires
        // customer_id NOT NULL) has nothing to attach to.
        $lead = $proposal->lead;
        $lead->update(['status' => 'won']);

        $customer = Customer::where('tenant_id', $proposal->tenant_id)
            ->where('email', $lead->email)
            ->first();

        if (!$customer) {
            $customer = Customer::create([
                'tenant_id' => $proposal->tenant_id,
                'branch_id' => $lead->branch_id,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'customer_type' => 'individual',
                'status' => 'active',
            ]);
        }

        $proposal->update(['customer_id' => $customer->id]);
        if ($proposal->quotation_id) {
            $proposal->quotation()->update(['customer_id' => $customer->id]);
        }

        return response()->json([
            'message' => 'Proposal signed, deal won, and customer created/linked',
            'data' => $proposal->load('quotation'),
            'customer' => $customer,
        ]);
    }

    public function rejectProposal(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        $proposal = Proposal::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $proposal->update([
            'status' => 'rejected',
        ]);

        $proposal->lead->update(['status' => 'lost']);

        return response()->json([
            'message' => 'Proposal rejected',
            'data' => $proposal
        ]);
    }

    public function getProposalStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stats = [
            'total' => Proposal::where('tenant_id', $tenantId)->count(),
            'draft' => Proposal::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'sent' => Proposal::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
            'signed' => Proposal::where('tenant_id', $tenantId)->where('status', 'signed')->count(),
            'rejected' => Proposal::where('tenant_id', $tenantId)->where('status', 'rejected')->count(),
            'conversion_rate' => Proposal::where('tenant_id', $tenantId)->count() > 0 
                ? round((Proposal::where('tenant_id', $tenantId)->where('status', 'signed')->count() / 
                        Proposal::where('tenant_id', $tenantId)->count()) * 100, 2)
                : 0,
        ];

        return response()->json(['data' => $stats]);
    }
}
