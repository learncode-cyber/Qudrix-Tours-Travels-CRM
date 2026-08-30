<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\ContactListMember;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Services\CampaignDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    public function __construct(private CampaignDispatcher $dispatcher)
    {
    }

    // ---------------- Contact lists ----------------
    public function contactLists(Request $request)
    {
        return response()->json([
            'data' => ContactList::where('tenant_id', $request->user->tenant_id)
                ->withCount('members')
                ->get(),
        ]);
    }

    public function storeContactList(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_dynamic' => 'boolean',
            'criteria' => 'nullable|array',
        ]);

        return response()->json([
            'data' => ContactList::create(['tenant_id' => $request->user->tenant_id, ...$validated]),
        ], 201);
    }

    public function addListMembers(Request $request, $id)
    {
        $list = ContactList::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:customers,id',
            'lead_ids' => 'nullable|array',
            'lead_ids.*' => 'exists:leads,id',
        ]);

        $added = 0;
        foreach ($validated['customer_ids'] ?? [] as $customerId) {
            ContactListMember::firstOrCreate(['contact_list_id' => $list->id, 'customer_id' => $customerId]);
            $added++;
        }
        foreach ($validated['lead_ids'] ?? [] as $leadId) {
            ContactListMember::firstOrCreate(['contact_list_id' => $list->id, 'lead_id' => $leadId]);
            $added++;
        }

        return response()->json(['data' => ['added' => $added, 'total_members' => $list->members()->count()]]);
    }

    // ---------------- Campaigns ----------------
    public function campaigns(Request $request)
    {
        $campaigns = Campaign::where('tenant_id', $request->user->tenant_id)
            ->withCount('recipients')
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => $campaigns->items(), 'total' => $campaigns->total()]);
    }

    public function storeCampaign(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'channel' => ['required', Rule::in(Campaign::CHANNELS)],
            'contact_list_id' => 'nullable|exists:contact_lists,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $campaign = Campaign::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'status' => isset($validated['scheduled_at']) ? 'scheduled' : 'draft',
            ...$validated,
        ]);

        return response()->json(['data' => $campaign], 201);
    }

    public function showCampaign(Request $request, $id)
    {
        $campaign = Campaign::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $campaign->toArray() + ['stats' => $campaign->stats()]]);
    }

    // Materialises recipients from the contact list, recording contacts
    // with no usable destination as skipped so the shortfall is visible.
    public function prepareCampaign(Request $request, $id)
    {
        $campaign = Campaign::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return response()->json(['error' => 'Only a draft or scheduled campaign can be prepared.'], 422);
        }
        if (!$campaign->contact_list_id) {
            return response()->json(['error' => 'Attach a contact list before preparing this campaign.'], 422);
        }

        $count = $this->dispatcher->buildRecipients($campaign);

        return response()->json(['data' => ['recipients_processed' => $count, 'stats' => $campaign->stats()]]);
    }

    public function sendCampaign(Request $request, $id)
    {
        $campaign = Campaign::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if (in_array($campaign->status, ['sending', 'sent'], true)) {
            return response()->json(['error' => 'This campaign has already been sent.'], 422);
        }
        if ($campaign->recipients()->where('status', 'pending')->doesntExist()) {
            return response()->json(['error' => 'No pending recipients. Prepare the campaign first.'], 422);
        }

        $result = $this->dispatcher->send($campaign);

        return response()->json([
            'data' => ['result' => $result, 'stats' => $campaign->fresh()->stats()],
            'note' => $result['skipped'] > 0
                ? 'Some recipients were skipped — see each recipient\'s failure_reason. A channel with no '
                    . 'configured provider skips rather than reporting a false success.'
                : null,
        ]);
    }

    public function campaignReport(Request $request, $id)
    {
        $campaign = Campaign::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => [
            'campaign' => $campaign->only(['id', 'name', 'channel', 'status', 'started_at', 'completed_at']),
            'stats' => $campaign->stats(),
            'failures' => $campaign->recipients()
                ->whereIn('status', ['failed', 'skipped'])
                ->limit(100)
                ->get(['destination', 'status', 'failure_reason']),
        ]]);
    }

    // ---------------- Coupons ----------------
    public function coupons(Request $request)
    {
        return response()->json([
            'data' => Coupon::where('tenant_id', $request->user->tenant_id)->latest()->get(),
        ]);
    }

    public function storeCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json(['error' => 'A percentage discount cannot exceed 100.'], 422);
        }

        return response()->json([
            'data' => Coupon::create([
                'tenant_id' => $request->user->tenant_id,
                'is_active' => true,
                'used_count' => 0,
                ...$validated,
            ]),
        ], 201);
    }

    // Validates without redeeming, so a UI can preview the discount.
    public function validateCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'booking_amount' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('tenant_id', $request->user->tenant_id)
            ->where('code', $validated['code'])
            ->first();

        if (!$coupon) {
            return response()->json(['data' => ['valid' => false, 'reason' => 'Unknown coupon code.']], 404);
        }

        $reason = $coupon->rejectionReasonFor((float) $validated['booking_amount']);

        return response()->json(['data' => [
            'valid' => $reason === null,
            'reason' => $reason,
            'discount' => $reason === null ? $coupon->discountFor((float) $validated['booking_amount']) : null,
        ]]);
    }

    // Redeems against a real booking. Validity, the discount amount, and
    // the usage counter are all handled server-side in one transaction so
    // a coupon cannot be over-redeemed by concurrent requests.
    public function redeemCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $tenantId = $request->user->tenant_id;
        $booking = Booking::where('tenant_id', $tenantId)->findOrFail($validated['booking_id']);

        return DB::transaction(function () use ($tenantId, $validated, $booking) {
            $coupon = Coupon::where('tenant_id', $tenantId)
                ->where('code', $validated['code'])
                ->lockForUpdate()
                ->first();

            if (!$coupon) {
                return response()->json(['error' => 'Unknown coupon code.'], 404);
            }

            $amount = (float) $booking->total_amount;
            $reason = $coupon->rejectionReasonFor($amount);
            if ($reason) {
                return response()->json(['error' => $reason], 422);
            }

            if (CouponRedemption::where('coupon_id', $coupon->id)->where('booking_id', $booking->id)->exists()) {
                return response()->json(['error' => 'This coupon is already applied to this booking.'], 422);
            }

            $discount = $coupon->discountFor($amount);

            $redemption = CouponRedemption::create([
                'tenant_id' => $tenantId,
                'coupon_id' => $coupon->id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'discount_applied' => $discount,
                'created_at' => now(),
            ]);

            $coupon->increment('used_count');

            return response()->json(['data' => [
                'redemption' => $redemption,
                'discount_applied' => $discount,
                'booking_amount_before' => $amount,
                'booking_amount_after' => round($amount - $discount, 2),
                'note' => 'The booking total is not modified here; apply the discount through the '
                    . 'quotation/invoice so the change stays auditable.',
            ]], 201);
        });
    }
}
