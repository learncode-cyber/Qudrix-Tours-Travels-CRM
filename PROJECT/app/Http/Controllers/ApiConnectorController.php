<?php
namespace App\Http\Controllers;

use App\Models\ApiConnector;
use App\Models\ApiConnectorEndpoint;
use App\Services\ApiConnectorService;
use App\Services\ConnectorException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiConnectorController extends Controller
{
    public function index(Request $request)
    {
        $connectors = ApiConnector::where('tenant_id', $request->user->tenant_id)
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->withCount('endpoints')
            ->get()
            ->map(fn (ApiConnector $c) => $c->toArray() + ['contract_required' => $c->isContractRequired()]);

        return response()->json(['data' => $connectors]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(ApiConnector::CATEGORIES)],
            'provider_name' => 'nullable|string|max:255',
            'base_url' => 'required|url|max:2048',
            'auth_type' => ['required', Rule::in(ApiConnector::AUTH_TYPES)],
            'auth_key_name' => 'nullable|string|max:255',
            'credentials' => 'nullable|array',
            'default_headers' => 'nullable|array',
            'timeout_seconds' => 'nullable|integer|min:1|max:' . config('integrations.max_timeout_seconds', 60),
        ]);

        $connector = ApiConnector::create([
            'tenant_id' => $request->user->tenant_id,
            'slug' => Str::slug($validated['name']) . '-' . Str::lower(Str::random(4)),
            'status' => empty($validated['credentials']) ? 'unconfigured' : 'configured',
            'is_active' => false,
            ...$validated,
        ]);

        return response()->json([
            'data' => $connector,
            'contract_required' => $connector->isContractRequired(),
            'message' => 'Connector created. Map at least one endpoint before it can be used.',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)
            ->with('endpoints')
            ->findOrFail($id);

        return response()->json([
            'data' => $connector,
            'contract_required' => $connector->isContractRequired(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'provider_name' => 'nullable|string|max:255',
            'base_url' => 'sometimes|url|max:2048',
            'auth_type' => ['sometimes', Rule::in(ApiConnector::AUTH_TYPES)],
            'auth_key_name' => 'nullable|string|max:255',
            'default_headers' => 'nullable|array',
            'timeout_seconds' => 'nullable|integer|min:1|max:' . config('integrations.max_timeout_seconds', 60),
            'is_active' => 'boolean',
        ]);

        // Activating a connector that has no mapped contract would let it
        // be selected in the UI and then fail at call time — refuse here.
        if (($validated['is_active'] ?? false) && $connector->isContractRequired()) {
            return response()->json([
                'error' => 'CONTRACT REQUIRED: map at least one active endpoint before activating this connector.',
            ], 422);
        }

        $connector->update($validated);

        return response()->json(['data' => $connector]);
    }

    // Credentials are written through a dedicated endpoint so they never
    // ride along in a general update and are never returned in a response.
    public function updateCredentials(Request $request, $id)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'credentials' => 'required|array',
        ]);

        $connector->update([
            'credentials' => $validated['credentials'],
            'status' => 'configured',
        ]);

        return response()->json(['message' => 'Credentials saved (encrypted at rest).']);
    }

    public function destroy(Request $request, $id)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $connector->delete();

        return response()->json(['message' => 'Connector deleted']);
    }

    public function upsertEndpoint(Request $request, $id)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'operation' => 'required|string|max:64',
            'http_method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'path' => 'required|string|max:2048',
            'request_template' => 'nullable|array',
            'query_template' => 'nullable|array',
            'response_mapping' => 'nullable|array',
            'response_collection_path' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $endpoint = ApiConnectorEndpoint::updateOrCreate(
            ['api_connector_id' => $connector->id, 'operation' => $validated['operation']],
            $validated + ['is_active' => $validated['is_active'] ?? true]
        );

        return response()->json(['data' => $endpoint], 201);
    }

    public function deleteEndpoint(Request $request, $id, $endpointId)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $endpoint = ApiConnectorEndpoint::where('api_connector_id', $connector->id)->findOrFail($endpointId);
        $endpoint->delete();

        return response()->json(['message' => 'Endpoint mapping deleted']);
    }

    public function testConnection(Request $request, $id, ApiConnectorService $service)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $result = $service->testConnection($connector, $request->user->id);

        return response()->json(['data' => $result], $result['connected'] ? 200 : 502);
    }

    // Runs a mapped operation against the live provider. Used by the
    // Flight/Hotel/Visa search surfaces and available directly for
    // operations the CRM does not model natively.
    public function execute(Request $request, $id, ApiConnectorService $service)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'operation' => 'required|string|max:64',
            'params' => 'nullable|array',
        ]);

        try {
            $result = $service->execute($connector, $validated['operation'], $validated['params'] ?? [], $request->user->id);
        } catch (ConnectorException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['data' => $result]);
    }

    public function callLogs(Request $request, $id)
    {
        $connector = ApiConnector::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $logs = $connector->callLogs()->latest('created_at')->paginate($request->per_page ?? 20);

        return response()->json(['data' => $logs->items()]);
    }
}
