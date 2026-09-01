<?php
namespace App\Http\Controllers;
use App\Models\Automation;
use App\Services\AutomationEngine;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    protected $engine;
    public function __construct(AutomationEngine $engine) { $this->engine = $engine; }
    
    public function index(Request $request)
    {
        $automations = Automation::where('tenant_id', $request->user->tenant_id)
            ->withCount('logs')
            ->paginate(20);
        return response()->json(['data' => $automations->items()]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'trigger_type' => 'required|in:booking_created,customer_added,invoice_created,payment_received,webhook',
            'status' => 'required|in:draft,active,paused,archived'
        ]);
        $automation = Automation::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $automation], 201);
    }
    
    public function show(Request $request, $id)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->with('steps')->findOrFail($id);
        return response()->json(['data' => $automation]);
    }
    
    public function update(Request $request, $id)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $automation->update($request->all());
        return response()->json(['data' => $automation]);
    }
    
    public function execute(Request $request, $id)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $result = $this->engine->execute($automation, $request->input('trigger_data', []));
        return response()->json(['data' => $result]);
    }
    
    public function test(Request $request, $id)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $result = $this->engine->test($automation, $request->input('test_data', []));
        return response()->json(['data' => $result]);
    }
}
