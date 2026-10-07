<?php
namespace App\Http\Controllers;
use App\Utilities\DatabaseOptimizer;
use App\Utilities\BackupManager;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $optimizer;
    protected $backupManager;
    
    public function __construct(DatabaseOptimizer $optimizer, BackupManager $backupManager)
    {
        $this->optimizer = $optimizer;
        $this->backupManager = $backupManager;
    }
    
    public function optimizeDatabase(Request $request)
    {
        $this->authorize('admin');
        $results = $this->optimizer->optimizeAllTables();
        return response()->json(['data' => $results]);
    }
    
    public function analyzeDatabase(Request $request)
    {
        $this->authorize('admin');
        $results = $this->optimizer->analyzeAllTables();
        return response()->json(['data' => $results]);
    }
    
    public function createBackup(Request $request)
    {
        $this->authorize('admin');
        $path = $this->backupManager->createBackup($request->input('name'));
        return response()->json(['backup_path' => $path]);
    }
    
    public function listBackups(Request $request)
    {
        $this->authorize('admin');
        $backups = $this->backupManager->listBackups();
        return response()->json(['backups' => $backups]);
    }
    
    public function cleanupOldBackups(Request $request)
    {
        $this->authorize('admin');
        $days = $request->input('days', 30);
        $deleted = $this->backupManager->deleteOldBackups($days);
        return response()->json(['deleted' => $deleted]);
    }
}
