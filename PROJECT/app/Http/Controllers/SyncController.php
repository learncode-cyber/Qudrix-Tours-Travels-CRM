<?php
namespace App\Http\Controllers;
use App\Models\OfflineSync;
use App\Services\SyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    protected $syncService;
    public function __construct(SyncService $syncService) { $this->syncService = $syncService; }
    
    public function syncData(Request $request)
    {
        $data = $request->input('changes', []);
        $batchId = $request->input('batch_id');
        $result = $this->syncService->processSync($request->user->tenant_id, $request->user->id, $data, $batchId);
        return response()->json(['data' => $result]);
    }
    
    public function getPendingSync(Request $request)
    {
        $pending = OfflineSync::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->where('status', 'pending')
            ->limit(100)
            ->get();
        return response()->json(['data' => $pending]);
    }
    
    public function getSyncStatus(Request $request, $batchId)
    {
        $status = $this->syncService->getBatchStatus($request->user->tenant_id, $batchId);
        return response()->json(['data' => $status]);
    }
    
    public function resyncFailed(Request $request)
    {
        $result = $this->syncService->retryFailed($request->user->tenant_id, $request->user->id);
        return response()->json(['data' => $result]);
    }
}
