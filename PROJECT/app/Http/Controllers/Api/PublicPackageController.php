<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public Package API Controller
 * Used by website to display packages
 */
class PublicPackageController extends Controller
{
    /**
     * Get all packages with filtering, pagination, and search
     * 
     * GET /api/v1/packages
     * 
     * Query Parameters:
     * - page: int (default: 1)
     * - limit: int (default: 10, max: 100)
     * - search: string (search by name)
     * - type: string (filter by type: hajj, umrah, tour, egypt)
     * - sort: string (created_at, price, duration_days)
     * - order: asc|desc (default: desc)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->get('page', 1));
            $limit = min(100, max(1, (int) $request->get('limit', 10)));
            $search = $request->get('search', '');
            $type = $request->get('type', '');
            $sort = $request->get('sort', 'created_at');
            $order = $request->get('order', 'desc');

            // Validate sort column
            $allowedSorts = ['created_at', 'price', 'duration_days', 'capacity', 'name'];
            $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
            $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

            $query = Package::where('is_active', true);

            // Apply search
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Apply type filter
            if (!empty($type)) {
                $query->where('type', $type);
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply sorting and pagination
            $packages = $query->orderBy($sort, $order)
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->map(fn($pkg) => $this->formatPackage($pkg));

            return response()->json([
                'success' => true,
                'data' => $packages,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                    'has_more' => ($page * $limit) < $total,
                ],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'api_version' => 'v1',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch packages',
                'code' => 'FETCH_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get single package details
     * 
     * GET /api/v1/packages/{id}
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $package = Package::where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package not found',
                    'code' => 'NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPackageDetail($package),
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'api_version' => 'v1',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch package',
                'code' => 'FETCH_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Format package for list view
     */
    private function formatPackage(Package $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'type' => $package->type,
            'description' => \Str::limit($package->description, 150),
            'price' => $package->price,
            'currency' => $package->currency ?? 'BDT',
            'duration_days' => $package->duration_days,
            'capacity' => $package->capacity,
            'bookings_count' => $package->bookings_count ?? 0,
            'image_url' => $package->image_url,
            'rating' => $package->rating ?? 0,
            'is_featured' => $package->is_featured ?? false,
            'created_at' => $package->created_at->toIso8601String(),
        ];
    }

    /**
     * Format package for detail view
     */
    private function formatPackageDetail(Package $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'type' => $package->type,
            'description' => $package->description,
            'price' => $package->price,
            'currency' => $package->currency ?? 'BDT',
            'duration_days' => $package->duration_days,
            'capacity' => $package->capacity,
            'available_seats' => max(0, $package->capacity - ($package->bookings_count ?? 0)),
            'bookings_count' => $package->bookings_count ?? 0,
            'image_url' => $package->image_url,
            'images' => $package->images ? json_decode($package->images, true) : [],
            'itinerary' => $package->itinerary ? json_decode($package->itinerary, true) : [],
            'inclusions' => $package->inclusions ? json_decode($package->inclusions, true) : [],
            'exclusions' => $package->exclusions ? json_decode($package->exclusions, true) : [],
            'highlights' => $package->highlights ? json_decode($package->highlights, true) : [],
            'terms_conditions' => $package->terms_conditions,
            'cancellation_policy' => $package->cancellation_policy,
            'rating' => $package->rating ?? 0,
            'reviews_count' => $package->reviews_count ?? 0,
            'is_featured' => $package->is_featured ?? false,
            'is_active' => $package->is_active,
            'created_at' => $package->created_at->toIso8601String(),
            'updated_at' => $package->updated_at->toIso8601String(),
        ];
    }
}
