<?php
namespace App\Http\Controllers;

use App\Models\TrackingConfig;
use Illuminate\Http\Request;

class TrackingConfigController extends Controller
{
    public function show(Request $request)
    {
        $config = TrackingConfig::where('tenant_id', $request->user->tenant_id)->first();

        return response()->json(['data' => $config, 'meta_configured' => $config?->metaConfigured() ?? false, 'ga4_configured' => $config?->ga4Configured() ?? false]);
    }

    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'meta_pixel_id' => 'nullable|string',
            'meta_conversions_api_token' => 'nullable|string',
            'ga4_measurement_id' => 'nullable|string',
            'ga4_api_secret' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $config = TrackingConfig::updateOrCreate(
            ['tenant_id' => $request->user->tenant_id],
            $validated
        );

        return response()->json(['data' => $config]);
    }
}
