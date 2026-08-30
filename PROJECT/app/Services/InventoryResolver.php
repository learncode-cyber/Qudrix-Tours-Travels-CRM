<?php
namespace App\Services;

use App\Models\Flight;
use App\Models\HotelRoomType;
use App\Models\Transport;
use Illuminate\Validation\ValidationException;

// Single source of truth for turning a requested component into a real,
// tenant-owned, currently-available inventory row with a real price.
//
// Every path that builds a package — the deterministic builder and the
// AI-assisted one — goes through here. That is what makes it structurally
// impossible for the AI layer to invent inventory or pricing: the AI can
// only ever *name* a component id, and this resolver decides whether that
// component actually exists, belongs to this tenant, and has capacity.
class InventoryResolver
{
    /**
     * @throws ValidationException when the component does not exist or lacks availability
     */
    public function resolve(int $tenantId, array $component): array
    {
        return match ($component['type']) {
            'hotel' => $this->resolveHotel($tenantId, $component),
            'flight' => $this->resolveFlight($tenantId, $component),
            'transport' => $this->resolveTransport($tenantId, $component),
            default => throw ValidationException::withMessages([
                'components' => "Unknown component type '{$component['type']}'",
            ]),
        };
    }

    /**
     * Resolves a whole list, summing the real cost.
     *
     * @return array{lines: array<int, array>, base_cost: float}
     */
    public function resolveAll(int $tenantId, array $components): array
    {
        $lines = [];
        $baseCost = 0.0;

        foreach ($components as $component) {
            $line = $this->resolve($tenantId, $component);
            $lines[] = $line;
            $baseCost += $line['line_total'];
        }

        return ['lines' => $lines, 'base_cost' => round($baseCost, 2)];
    }

    private function resolveHotel(int $tenantId, array $component): array
    {
        $roomType = HotelRoomType::where('tenant_id', $tenantId)->find($component['reference_id']);
        if (!$roomType) {
            throw ValidationException::withMessages([
                'components' => "Hotel room type #{$component['reference_id']} does not exist in your inventory.",
            ]);
        }
        if (!$roomType->isAvailable($component['quantity'])) {
            throw ValidationException::withMessages([
                'components' => "Not enough availability for hotel room type #{$roomType->id}.",
            ]);
        }

        return [
            'type' => 'hotel',
            'reference_id' => $roomType->id,
            'description' => ($roomType->hotel?->name ?? 'Hotel') . ' — ' . $roomType->name,
            'quantity' => $component['quantity'],
            'unit_price' => (float) $roomType->price_per_night,
            'line_total' => round((float) $roomType->price_per_night * $component['quantity'], 2),
        ];
    }

    private function resolveFlight(int $tenantId, array $component): array
    {
        $flight = Flight::where('tenant_id', $tenantId)->find($component['reference_id']);
        if (!$flight) {
            throw ValidationException::withMessages([
                'components' => "Flight #{$component['reference_id']} does not exist in your inventory.",
            ]);
        }
        if ($flight->available_seats < $component['quantity']) {
            throw ValidationException::withMessages([
                'components' => "Not enough seats on flight #{$flight->id}.",
            ]);
        }

        return [
            'type' => 'flight',
            'reference_id' => $flight->id,
            'description' => $flight->airline_code . ' ' . $flight->flight_number
                . ' (' . $flight->departure_airport . '-' . $flight->arrival_airport . ')',
            'quantity' => $component['quantity'],
            'unit_price' => (float) $flight->price_per_seat,
            'line_total' => round((float) $flight->price_per_seat * $component['quantity'], 2),
        ];
    }

    private function resolveTransport(int $tenantId, array $component): array
    {
        $transport = Transport::where('tenant_id', $tenantId)->find($component['reference_id']);
        if (!$transport) {
            throw ValidationException::withMessages([
                'components' => "Transport #{$component['reference_id']} does not exist in your inventory.",
            ]);
        }
        if ($transport->capacity < $component['quantity']) {
            throw ValidationException::withMessages([
                'components' => "Not enough capacity on transport #{$transport->id}.",
            ]);
        }

        return [
            'type' => 'transport',
            'reference_id' => $transport->id,
            'description' => $transport->vehicle_name
                . ' (' . $transport->pickup_location . ' -> ' . $transport->dropoff_location . ')',
            'quantity' => $component['quantity'],
            'unit_price' => (float) $transport->price_per_seat,
            'line_total' => round((float) $transport->price_per_seat * $component['quantity'], 2),
        ];
    }
}
