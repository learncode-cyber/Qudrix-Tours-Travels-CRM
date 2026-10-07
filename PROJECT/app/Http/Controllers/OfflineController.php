<?php
namespace App\Http\Controllers;
use App\Models\OfflineData;
use App\Services\OfflineDataService;
use Illuminate\Http\Request;

class OfflineController extends Controller
{
    protected $offlineService;
    public function __construct(OfflineDataService $offlineService) { $this->offlineService = $offlineService; }
    
    public function downloadOfflineData(Request $request)
    {
        $dataType = $request->input('type', 'all');
        $data = $this->offlineService->prepareOfflineData($request->user->tenant_id, $dataType);
        return response()->json(['data' => $data]);
    }
    
    public function getOfflineStatus(Request $request)
    {
        $status = $this->offlineService->getOfflineStatus($request->user->tenant_id, $request->user->id);
        return response()->json(['data' => $status]);
    }
    
    public function syncOfflineChanges(Request $request)
    {
        $changes = $request->input('changes', []);
        $result = $this->offlineService->syncOfflineChanges($request->user->tenant_id, $request->user->id, $changes);
        return response()->json(['data' => $result]);
    }
    
    public function clearOfflineData(Request $request)
    {
        $this->offlineService->clearOfflineData($request->user->tenant_id, $request->user->id);
        return response()->json(['message' => 'Offline data cleared']);
    }
}
