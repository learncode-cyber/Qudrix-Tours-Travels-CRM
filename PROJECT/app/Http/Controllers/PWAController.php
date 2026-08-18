<?php
namespace App\Http\Controllers;
use App\Models\PWASettings;
use App\Services\PWAService;
use Illuminate\Http\Request;

class PWAController extends Controller
{
    protected $pwaService;
    public function __construct(PWAService $pwaService) { $this->pwaService = $pwaService; }
    
    public function getManifest(Request $request)
    {
        $settings = PWASettings::where('tenant_id', $request->user->tenant_id)->firstOrCreate([
            'tenant_id' => $request->user->tenant_id,
            'app_name' => 'QUDRIX Travel CRM',
            'app_short_name' => 'QUDRIX',
            'is_enabled' => true
        ]);
        $manifest = $this->pwaService->generateManifest($settings);
        return response()->json($manifest);
    }
    
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'nullable|string',
            'description' => 'nullable|string',
            'theme_color' => 'nullable|string',
            'background_color' => 'nullable|string',
            'offline_enabled' => 'nullable|boolean',
            'push_enabled' => 'nullable|boolean'
        ]);
        $settings = PWASettings::where('tenant_id', $request->user->tenant_id)->first() ??
            PWASettings::create(['tenant_id' => $request->user->tenant_id]);
        $settings->update($validated);
        return response()->json(['data' => $settings]);
    }
    
    public function getServiceWorker(Request $request)
    {
        $sw = $this->pwaService->getServiceWorkerCode($request->user->tenant_id);
        return response($sw, 200, ['Content-Type' => 'application/javascript']);
    }
}
