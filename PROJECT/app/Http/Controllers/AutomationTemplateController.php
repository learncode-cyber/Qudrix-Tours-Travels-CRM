<?php
namespace App\Http\Controllers;
use App\Models\AutomationTemplate;
use Illuminate\Http\Request;

class AutomationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = AutomationTemplate::where('tenant_id', $request->user->tenant_id)
            ->where('status', 'active')
            ->paginate(20);
        return response()->json(['data' => $templates->items()]);
    }
    
    public function show(Request $request, $id)
    {
        $template = AutomationTemplate::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $template]);
    }
    
    public function getByCategory(Request $request, $category)
    {
        $templates = AutomationTemplate::where('tenant_id', $request->user->tenant_id)
            ->where('category', $category)
            ->where('status', 'active')
            ->get();
        return response()->json(['data' => $templates]);
    }
    
    public function useTemplate(Request $request, $templateId)
    {
        $template = AutomationTemplate::where('tenant_id', $request->user->tenant_id)->findOrFail($templateId);
        $template->increment('usage_count');
        return response()->json(['data' => $template->workflow_config]);
    }
}
