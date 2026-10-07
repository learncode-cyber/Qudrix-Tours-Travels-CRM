<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationTemplate::where('tenant_id', $request->user->tenant_id);

        if ($request->event_key) {
            $query->where('event_key', $request->event_key);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_key' => 'required|string|max:100',
            'channel' => 'required|in:in_app,email,sms,whatsapp,telegram',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $template = NotificationTemplate::updateOrCreate(
            [
                'tenant_id' => $request->user->tenant_id,
                'event_key' => $validated['event_key'],
                'channel' => $validated['channel'],
            ],
            $validated
        );

        return response()->json(['data' => $template], 201);
    }

    public function delete(Request $request, $id)
    {
        $template = NotificationTemplate::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted']);
    }
}
