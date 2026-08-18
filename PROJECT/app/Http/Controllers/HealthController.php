<?php
namespace App\Http\Controllers;
use App\Monitoring\HealthCheck;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    protected $healthCheck;
    
    public function __construct(HealthCheck $healthCheck)
    {
        $this->healthCheck = $healthCheck;
    }
    
    public function status()
    {
        $health = $this->healthCheck->getSystemHealth();
        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        return response()->json([
            'status' => $health['status'],
            'checks' => $health,
            'timestamp' => now()
        ], $statusCode);
    }
    
    public function detailed()
    {
        return response()->json($this->healthCheck->getSystemHealth());
    }
}
