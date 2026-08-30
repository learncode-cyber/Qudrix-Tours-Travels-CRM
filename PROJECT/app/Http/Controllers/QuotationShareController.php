<?php
namespace App\Http\Controllers;
use App\Models\Quotation;
use Illuminate\Http\Request;

// Public, unauthenticated view for a customer-facing quotation link
// (Directive §3.C: "Quotation must be shareable with customers"). Access
// is gated only by the unguessable share_token — never by tenant/session
// auth, since the recipient has no CRM account.
class QuotationShareController extends Controller
{
    public function show(Request $request, $token)
    {
        $quotation = Quotation::where('share_token', $token)
            ->whereNotIn('status', ['draft', 'pending_approval'])
            ->with('items', 'customer')
            ->firstOrFail();

        return response()->json(['data' => $quotation]);
    }

    public function accept(Request $request, $token)
    {
        $quotation = Quotation::where('share_token', $token)
            ->where('status', 'sent')
            ->firstOrFail();

        if ($quotation->isExpired()) {
            return response()->json(['error' => 'This quotation has expired'], 400);
        }

        $quotation->update(['status' => 'accepted']);
        return response()->json(['data' => $quotation]);
    }

    public function reject(Request $request, $token)
    {
        $quotation = Quotation::where('share_token', $token)
            ->where('status', 'sent')
            ->firstOrFail();

        $validated = $request->validate(['reason' => 'nullable|string']);
        $quotation->update([
            'status' => 'rejected',
            'notes' => trim(($quotation->notes ?? '') . "\nCustomer rejection reason: " . ($validated['reason'] ?? 'not provided')),
        ]);
        return response()->json(['data' => $quotation]);
    }
}
