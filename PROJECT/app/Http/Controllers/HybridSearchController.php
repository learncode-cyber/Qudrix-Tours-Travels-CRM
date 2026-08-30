<?php
namespace App\Http\Controllers;

use App\Models\ApiConnector;
use App\Models\Flight;
use App\Models\Hotel;
use App\Services\ApiConnectorService;
use App\Services\ConnectorException;
use Illuminate\Http\Request;

// Hybrid inventory search: queries the CRM's own inventory AND every
// active operator-configured external provider for the category, then
// returns both sets clearly labelled by source.
//
// Nothing here fabricates availability. Internal results are real database
// rows; external results are whatever the operator's own configured
// provider actually returned, mapped through that connector's
// response_mapping. A provider that errors is reported as an error against
// that source — the search does not silently drop it or invent a result.
class HybridSearchController extends Controller
{
    public function __construct(private ApiConnectorService $connectors)
    {
    }

    public function flights(Request $request)
    {
        $validated = $request->validate([
            'departure_airport' => 'nullable|string|size:3',
            'arrival_airport' => 'nullable|string|size:3',
            'departure_date' => 'nullable|date',
            'passengers' => 'nullable|integer|min:1',
            'include_external' => 'boolean',
        ]);

        $internal = Flight::where('tenant_id', $request->user->tenant_id)
            ->when($validated['departure_airport'] ?? null, fn ($q, $v) => $q->where('departure_airport', $v))
            ->when($validated['arrival_airport'] ?? null, fn ($q, $v) => $q->where('arrival_airport', $v))
            ->when($validated['departure_date'] ?? null, fn ($q, $v) => $q->whereDate('departure_date', $v))
            ->when($validated['passengers'] ?? null, fn ($q, $v) => $q->where('available_seats', '>=', $v))
            ->where('status', 'active')
            ->limit(50)
            ->get()
            ->map(fn (Flight $f) => [
                'source' => 'internal',
                'source_name' => 'Own inventory',
                'reference_id' => $f->id,
                'airline' => $f->airline_code,
                'flight_number' => $f->flight_number,
                'departure_airport' => $f->departure_airport,
                'arrival_airport' => $f->arrival_airport,
                'departure_date' => $f->departure_date,
                'price' => (float) $f->price_per_seat,
                'currency' => $f->currency,
                'available_seats' => $f->available_seats,
                'bookable_in_crm' => true,
            ])
            ->all();

        $external = ($validated['include_external'] ?? true)
            ? $this->queryExternal($request, 'flight', $validated)
            : ['results' => [], 'errors' => []];

        return response()->json(['data' => [
            'internal' => $internal,
            'external' => $external['results'],
            'external_errors' => $external['errors'],
            'total' => count($internal) + count($external['results']),
        ]]);
    }

    public function hotels(Request $request)
    {
        $validated = $request->validate([
            'city' => 'nullable|string',
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'nullable|date|after:check_in_date',
            'rooms' => 'nullable|integer|min:1',
            'include_external' => 'boolean',
        ]);

        $internal = Hotel::where('tenant_id', $request->user->tenant_id)
            ->when($validated['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            ->where('status', 'active')
            ->with('roomTypes')
            ->limit(50)
            ->get()
            ->map(fn (Hotel $h) => [
                'source' => 'internal',
                'source_name' => 'Own inventory',
                'reference_id' => $h->id,
                'name' => $h->name,
                'city' => $h->city,
                'country' => $h->country,
                'star_rating' => $h->star_rating,
                'price_from' => (float) ($h->roomTypes->min('price_per_night') ?? $h->price_per_night),
                'currency' => $h->currency,
                'room_types' => $h->roomTypes->map(fn ($rt) => [
                    'id' => $rt->id,
                    'name' => $rt->name,
                    'price_per_night' => (float) $rt->price_per_night,
                    'available_rooms' => $rt->available_rooms,
                ])->all(),
                'bookable_in_crm' => true,
            ])
            ->all();

        $external = ($validated['include_external'] ?? true)
            ? $this->queryExternal($request, 'hotel', $validated)
            : ['results' => [], 'errors' => []];

        return response()->json(['data' => [
            'internal' => $internal,
            'external' => $external['results'],
            'external_errors' => $external['errors'],
            'total' => count($internal) + count($external['results']),
        ]]);
    }

    // Visa has no internal "inventory" to search — this surfaces whatever
    // the operator's configured visa providers report (requirements, fees,
    // processing time), and says so plainly when none are configured.
    public function visa(Request $request)
    {
        $validated = $request->validate([
            'destination_country' => 'required|string|size:2',
            'nationality' => 'nullable|string|size:2',
            'visa_type' => 'nullable|string',
        ]);

        $external = $this->queryExternal($request, 'visa', $validated);

        return response()->json(['data' => [
            'external' => $external['results'],
            'external_errors' => $external['errors'],
            'providers_configured' => $external['providers_queried'],
        ]]);
    }

    /**
     * Fans a search out to every active connector in a category.
     */
    private function queryExternal(Request $request, string $category, array $params): array
    {
        $connectors = ApiConnector::where('tenant_id', $request->user->tenant_id)
            ->where('category', $category)
            ->where('is_active', true)
            ->get();

        $results = [];
        $errors = [];
        $queried = 0;

        foreach ($connectors as $connector) {
            if (!$connector->endpointFor('search')) {
                // Not an error: this connector is configured for other
                // operations (book/status) but not search.
                continue;
            }

            $queried++;

            try {
                $response = $this->connectors->execute($connector, 'search', $params, $request->user->id);
                $mapped = $response['mapped'];

                // A mapped list response comes back as a list of rows; a
                // single-object mapping is wrapped so the shape is stable.
                $rows = array_is_list($mapped) ? $mapped : [$mapped];

                foreach ($rows as $row) {
                    $results[] = array_merge(
                        ['source' => 'external', 'source_name' => $connector->provider_name ?: $connector->name, 'connector_id' => $connector->id, 'bookable_in_crm' => (bool) $connector->endpointFor('book')],
                        is_array($row) ? $row : ['value' => $row]
                    );
                }
            } catch (ConnectorException $e) {
                $errors[] = [
                    'connector_id' => $connector->id,
                    'source_name' => $connector->provider_name ?: $connector->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['results' => $results, 'errors' => $errors, 'providers_queried' => $queried];
    }
}
