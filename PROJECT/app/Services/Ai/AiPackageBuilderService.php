<?php
namespace App\Services\Ai;

use App\Models\Flight;
use App\Models\HotelRoomType;
use App\Models\Transport;
use App\Services\InventoryResolver;
use App\Services\PricingEngine;

// AI-assisted Custom Package Builder (Directive S6).
//
// GROUNDING CONTRACT — the whole point of this class:
//   1. The AI's ONLY jobs are (a) turning free text into structured
//      requirements and (b) choosing among components it was SHOWN.
//   2. Every component the AI names is re-resolved against real inventory
//      by InventoryResolver. A component the AI hallucinated simply does
//      not resolve and the request fails loudly.
//   3. The AI never sets a price. Cost comes from real unit prices; the
//      final figure comes from the deterministic PricingEngine.
//   4. The result is a DRAFT for human approval, never a confirmed booking.
//
// This is why the directive's "never allow AI to invent inventory or
// pricing" rule holds structurally here rather than by prompt instruction.
class AiPackageBuilderService
{
    public function __construct(
        private AiGateway $gateway,
        private InventoryResolver $inventory,
        private PricingEngine $pricing,
    ) {
    }

    /**
     * Step 1 — interpret free-text requirements into structured parameters.
     * Returns only parameters; no inventory or pricing claims.
     */
    public function interpretRequirements(int $tenantId, string $text, ?int $userId = null): array
    {
        $system = <<<'PROMPT'
You extract structured travel requirements from a customer's message.
Return ONLY a JSON object, no prose and no code fences, with these keys:
{"destination": string|null, "travel_date": "YYYY-MM-DD"|null,
 "return_date": "YYYY-MM-DD"|null, "group_size": integer|null,
 "budget_amount": number|null, "budget_currency": string|null,
 "needs": {"flight": boolean, "hotel": boolean, "transport": boolean},
 "notes": string|null, "missing_information": [string]}

Rules:
- Extract only what the message actually states. Use null for anything not stated.
- Never invent dates, prices, airlines, or hotels.
- List anything a travel agent would still need to ask in "missing_information".
PROMPT;

        $result = $this->gateway->complete(
            $tenantId,
            'package_builder.interpret',
            [['role' => 'user', 'content' => $text]],
            $system,
            ['max_tokens' => 1024],
            $userId,
        );

        return $this->decodeJson($result->text);
    }

    /**
     * Step 2 — show the AI ONLY real, available inventory and let it
     * propose a combination. Anything it names is verified in step 3.
     *
     * @return array{proposal: array, verified: array, pricing: array}
     */
    public function proposePackage(int $tenantId, array $requirements, ?int $userId = null): array
    {
        $catalogue = $this->availableInventory($tenantId, $requirements);

        if (empty($catalogue['flights']) && empty($catalogue['hotels']) && empty($catalogue['transport'])) {
            return [
                'proposal' => null,
                'verified' => [],
                'pricing' => null,
                'message' => 'No matching inventory is currently available for these requirements. '
                    . 'Nothing was proposed — add inventory or configure an external provider first.',
            ];
        }

        $system = <<<'PROMPT'
You are a travel packaging assistant. You will be given a customer's
requirements and a CATALOGUE of inventory that actually exists and is
currently available.

Return ONLY a JSON object, no prose and no code fences:
{"components": [{"type": "flight"|"hotel"|"transport",
                 "reference_id": integer, "quantity": integer,
                 "why": string}],
 "alternatives": [{"reference_id": integer, "type": string, "why": string}],
 "upsell_suggestions": [string],
 "summary": string}

Absolute rules:
- You may ONLY use reference_id values that appear in the catalogue. Never
  invent an id, a hotel, a flight, an airline, or a price.
- Do not state any price or total in "summary" — pricing is calculated by
  the system, not by you.
- If the catalogue cannot satisfy a requirement, say so plainly in
  "summary" instead of substituting something that does not exist.
PROMPT;

        $userMessage = json_encode([
            'requirements' => $requirements,
            'catalogue' => $catalogue,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $result = $this->gateway->complete(
            $tenantId,
            'package_builder.propose',
            [['role' => 'user', 'content' => $userMessage]],
            $system,
            ['max_tokens' => 2048],
            $userId,
        );

        $proposal = $this->decodeJson($result->text);

        // Step 3 — verification. Every component the AI named is resolved
        // against real inventory here. A hallucinated id throws.
        $components = [];
        foreach ($proposal['components'] ?? [] as $c) {
            if (!isset($c['type'], $c['reference_id'], $c['quantity'])) {
                continue;
            }
            $components[] = [
                'type' => $c['type'],
                'reference_id' => (int) $c['reference_id'],
                'quantity' => max(1, (int) $c['quantity']),
            ];
        }

        if (empty($components)) {
            return [
                'proposal' => $proposal,
                'verified' => [],
                'pricing' => null,
                'message' => 'The assistant did not propose any usable component from the available catalogue.',
            ];
        }

        $resolved = $this->inventory->resolveAll($tenantId, $components);

        // Step 4 — price deterministically. The AI has no influence here.
        $travelDate = $requirements['travel_date'] ?? null;
        $daysBefore = $travelDate
            ? max(0, (int) now()->diffInDays(\Illuminate\Support\Carbon::parse($travelDate), false))
            : null;

        $pricing = $this->pricing->calculate($tenantId, $resolved['base_cost'], array_filter([
            'travel_date' => $travelDate,
            'group_size' => $requirements['group_size'] ?? null,
            'booking_days_before_travel' => $daysBefore,
        ], fn ($v) => $v !== null), $userId);

        return [
            'proposal' => $proposal,
            'verified' => $resolved['lines'],
            'components' => $components,
            'pricing' => $pricing,
            'requires_human_approval' => true,
        ];
    }

    /**
     * Real, currently-available inventory only — this is the only thing the
     * model is ever shown, so it has nothing to hallucinate from.
     */
    private function availableInventory(int $tenantId, array $requirements): array
    {
        $groupSize = max(1, (int) ($requirements['group_size'] ?? 1));

        $flights = Flight::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('available_seats', '>=', $groupSize)
            ->when($requirements['travel_date'] ?? null, fn ($q, $d) => $q->whereDate('departure_date', '>=', $d))
            ->limit(25)
            ->get()
            ->map(fn (Flight $f) => [
                'reference_id' => $f->id,
                'airline' => $f->airline_code,
                'flight_number' => $f->flight_number,
                'from' => $f->departure_airport,
                'to' => $f->arrival_airport,
                'departure_date' => optional($f->departure_date)->toDateString(),
                'price_per_seat' => (float) $f->price_per_seat,
                'currency' => $f->currency,
                'available_seats' => $f->available_seats,
            ])->all();

        $hotels = HotelRoomType::where('tenant_id', $tenantId)
            ->where('available_rooms', '>', 0)
            ->with('hotel')
            ->when($requirements['destination'] ?? null, function ($q, $destination) {
                $q->whereHas('hotel', fn ($h) => $h->where('city', 'like', "%{$destination}%")
                    ->orWhere('country', 'like', "%{$destination}%"));
            })
            ->limit(25)
            ->get()
            ->map(fn (HotelRoomType $rt) => [
                'reference_id' => $rt->id,
                'hotel' => $rt->hotel?->name,
                'city' => $rt->hotel?->city,
                'room_type' => $rt->name,
                'capacity' => $rt->capacity,
                'price_per_night' => (float) $rt->price_per_night,
                'currency' => $rt->currency,
                'available_rooms' => $rt->available_rooms,
            ])->all();

        $transport = Transport::where('tenant_id', $tenantId)
            ->where('capacity', '>=', $groupSize)
            ->limit(25)
            ->get()
            ->map(fn (Transport $t) => [
                'reference_id' => $t->id,
                'vehicle' => $t->vehicle_name,
                'from' => $t->pickup_location,
                'to' => $t->dropoff_location,
                'capacity' => $t->capacity,
                'price_per_seat' => (float) $t->price_per_seat,
                'currency' => $t->currency,
            ])->all();

        return ['flights' => $flights, 'hotels' => $hotels, 'transport' => $transport];
    }

    /**
     * Models sometimes wrap JSON in prose or code fences despite
     * instructions; recover the object rather than failing the request.
     */
    private function decodeJson(string $text): array
    {
        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new AiProviderException('The AI response was not valid JSON: ' . mb_strcut($text, 0, 500));
    }
}
