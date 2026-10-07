<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

/**
 * PHASE 18 AUDIT FINDING: app/Http/Controllers/Api/TenantController.php
 * existed with `namespace App\Http\Controllers\API;` (capital API vs the
 * actual folder `Api`) — the same PSR-4 case-mismatch bug found in
 * Phase 1's dead AuthController. Worse: it was never routed anywhere,
 * and for good reason — its index() called Tenant::paginate(15) with
 * NO tenant scoping at all, meaning any caller could see every tenant's
 * name, email, and slug. If it had ever been wired up, this would have
 * been a cross-tenant data leak, the most serious violation possible in
 * a multi-tenant system. Removed entirely and replaced with this
 * tenant-scoped version: a caller can only ever see/update their OWN
 * tenant, never list or access another.
 */
class TenantController extends Controller
{
    public function show(Request $request)
    {
        $tenant = Tenant::with('subscriptionPlan')->findOrFail($request->user->tenant_id);
        return response()->json(['data' => $tenant]);
    }

    public function update(Request $request)
    {
        $tenant = Tenant::findOrFail($request->user->tenant_id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'timezone' => 'sometimes|string|max:100',
            'currency' => 'sometimes|string|size:3',
            'language' => 'sometimes|string|max:10',
            'logo_url' => 'nullable|url',
            'primary_color' => 'nullable|string|max:7',
        ]);

        $tenant->update($validated);

        return response()->json(['data' => $tenant]);
    }

    /**
     * Public plan catalog — every tenant can see what's available to
     * upgrade to. Not tenant-scoped by design (plans are global, not
     * per-tenant data).
     */
    public function plans()
    {
        return response()->json(['data' => SubscriptionPlan::where('is_active', true)->get()]);
    }
}
