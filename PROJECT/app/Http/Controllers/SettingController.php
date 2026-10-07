<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * PHASE 7 AUDIT FINDING: Settings (used since Phase 3 for the quotation
 * approval threshold, and now for SMS/WhatsApp/Telegram credentials)
 * had no controller anywhere — nothing could set a value via the API.
 * Sensitive-looking keys are not specially encrypted here (matching how
 * ApiSettings/AI credentials are handled elsewhere would need its own
 * encryption-at-rest pass) — flagged, not silently assumed secure.
 */
class SettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = Setting::where('tenant_id', $request->user->tenant_id)->get();

        return response()->json(['data' => $settings]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $setting = Setting::updateOrCreate(
            ['tenant_id' => $request->user->tenant_id, 'key' => $validated['key']],
            [
                'value' => $validated['value'] ?? null,
                'type' => $validated['type'] ?? 'string',
                'description' => $validated['description'] ?? null,
            ]
        );

        return response()->json(['data' => $setting], 201);
    }

    public function delete(Request $request, $key)
    {
        Setting::where('tenant_id', $request->user->tenant_id)->where('key', $key)->delete();

        return response()->json(['message' => 'Setting deleted']);
    }
}
